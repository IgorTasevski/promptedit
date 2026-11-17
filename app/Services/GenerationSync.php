<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pricing\PriceCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerationSync
{
    public const string SUBMISSION_FAILED = 'submission_failed';

    private const int IMAGE_LIFETIME_HOURS = 24;

    private const string SUBMISSION_FAILED_MESSAGE = 'Pixelforge stayed too busy to accept this image, so it was not created. You were not charged.';

    public function __construct(private readonly PriceCalculator $calculator) {}

    /**
     * @param  array<string, mixed>  $remote
     */
    public function apply(array $remote): void
    {
        DB::transaction(function () use ($remote) {
            $generationId = $remote['metadata']['generation_id'] ?? null;
            $generation = is_string($generationId)
                ? Generation::query()->lockForUpdate()->find($generationId)
                : null;

            if ($generation === null) {
                Log::warning('Pixelforge generation has no matching record.', [
                    'pixelforge_id' => $remote['id'] ?? null,
                    'generation_id' => $generationId,
                ]);

                return;
            }

            $generation->pixelforge_id ??= $remote['id'];
            $status = GenerationStatus::from($remote['status']);
            $wasAbandoned = $this->wasAbandonedBeforeConfirmation($generation);

            if ($generation->status->canTransitionTo($status) || ($wasAbandoned && $status->isFinal())) {
                $this->transition($generation, $status, $remote, $wasAbandoned ? 0 : $generation->estimated_max);
            }

            $generation->save();
        });
    }

    public function abandonSubmission(Generation $generation): void
    {
        $this->reject($generation, self::SUBMISSION_FAILED, self::SUBMISSION_FAILED_MESSAGE);
    }

    public function reject(Generation $generation, string $errorCode, string $errorMessage): void
    {
        DB::transaction(function () use ($generation, $errorCode, $errorMessage) {
            $locked = Generation::query()->lockForUpdate()->findOrFail($generation->id);

            if ($locked->status !== GenerationStatus::Pending) {
                return;
            }

            $locked->update([
                'status' => GenerationStatus::Failed,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'completed_at' => now(),
            ]);
        });
    }

    private function wasAbandonedBeforeConfirmation(Generation $generation): bool
    {
        return $generation->status === GenerationStatus::Failed && $generation->error_code === self::SUBMISSION_FAILED;
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function transition(Generation $generation, GenerationStatus $status, array $remote, int $priceCeiling): void
    {
        $generation->fill([
            'status' => $status,
            'rates' => $remote['rates'],
            'started_at' => $remote['started_at'],
            'completed_at' => $remote['completed_at'],
            'error_code' => null,
            'error_message' => null,
        ]);

        match ($status) {
            GenerationStatus::Succeeded => $this->settle($generation, $remote, $priceCeiling),
            GenerationStatus::Failed => $generation->fill([
                'error_code' => $remote['error']['code'],
                'error_message' => $remote['error']['message'],
            ]),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    private function settle(Generation $generation, array $remote, int $priceCeiling): void
    {
        $usage = $remote['usage'];
        $providerCost = $this->calculator->providerCost($usage, $remote['rates']);
        $price = min($this->calculator->userPrice($providerCost), $priceCeiling);

        $generation->fill([
            'input_text_tokens' => $usage['input_text_tokens'],
            'input_image_tokens' => $usage['input_image_tokens'],
            'output_tokens' => $usage['output_tokens'],
            'reasoning_tokens' => $usage['reasoning_tokens'],
            'provider_cost' => $providerCost,
            'price' => $price,
            'image_url' => $remote['image_url'],
            'image_url_expires_at' => $remote['image_url_expires_at']
                ?? CarbonImmutable::parse($remote['completed_at'])->addHours(self::IMAGE_LIFETIME_HOURS),
        ]);

        if ($price === 0) {
            return;
        }

        $generation->user->creditTransactions()->create([
            'type' => CreditTransactionType::Charge,
            'amount' => -$price,
            'generation_id' => $generation->id,
        ]);
    }
}
