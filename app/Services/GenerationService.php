<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Exceptions\InsufficientCreditsException;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PricingCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class GenerationService
{
    public function __construct(
        private readonly PricingCatalog $catalog,
        private readonly PriceCalculator $calculator,
        private readonly CreditService $credits,
    ) {}

    private const int RECENT_LIMIT = 4;

    /**
     * @return Collection<int, Generation>
     */
    public function history(User $user): Collection
    {
        return $user->generations()->latest()->get();
    }

    /**
     * @return Collection<int, Generation>
     */
    public function recent(User $user): Collection
    {
        return $user->generations()->latest()->limit(self::RECENT_LIMIT)->get();
    }

    /**
     * @return array{lines: list<array{type: string, tokens: int, rate: string, cost: int}>, provider_cost: int, markup: int, waived: int, price: int}|null
     */
    public function costBreakdown(Generation $generation): ?array
    {
        if ($generation->status !== GenerationStatus::Succeeded) {
            return null;
        }

        return $this->calculator->breakdown(
            $generation->tokenUsage(),
            $generation->rates,
            $generation->provider_cost,
            $generation->price,
        );
    }

    /**
     * @param  list<string>  $referenceImages
     * @return array{min: int, max: int}
     */
    public function estimate(string $prompt, Size $size, Quality $quality, array $referenceImages): array
    {
        return $this->calculator->estimate($this->catalog->current(), $prompt, $size, $quality, count($referenceImages));
    }

    public function retry(Generation $failed): Generation
    {
        return $this->create($failed->user, $failed->prompt, $failed->size, $failed->quality, $failed->reference_images);
    }

    /**
     * @param  list<string>  $referenceImages
     */
    public function create(User $user, string $prompt, Size $size, Quality $quality, array $referenceImages): Generation
    {
        $estimate = $this->estimate($prompt, $size, $quality, $referenceImages);

        $generation = DB::transaction(function () use ($user, $prompt, $size, $quality, $referenceImages, $estimate) {
            User::query()->lockForUpdate()->findOrFail($user->id);

            $available = $this->credits->available($user);

            if ($available < $estimate['max']) {
                throw new InsufficientCreditsException($estimate['max'], $available);
            }

            return $user->generations()->create([
                'status' => GenerationStatus::Pending,
                'prompt' => $prompt,
                'size' => $size,
                'quality' => $quality,
                'reference_images' => $referenceImages,
                'estimated_min' => $estimate['min'],
                'estimated_max' => $estimate['max'],
            ]);
        });

        SubmitGeneration::dispatch($generation);

        return $generation;
    }
}
