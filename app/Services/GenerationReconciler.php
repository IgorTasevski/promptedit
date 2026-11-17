<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GenerationReconciler
{
    private const int STALE_AFTER_MINUTES = 2;

    private const int SUBMISSION_GRACE_MINUTES = 5;

    private const int RECOVERY_WINDOW_MINUTES = 10;

    public function __construct(
        private readonly PixelforgeClient $client,
        private readonly GenerationSync $sync,
    ) {}

    public function reconcileStale(): int
    {
        $this->abandonStuckSubmissions();
        $this->recoverGivenUpSubmissions();

        $stale = Generation::query()
            ->whereNotNull('pixelforge_id')
            ->where('updated_at', '<', now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->where(fn ($query) => $query
                ->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Processing])
                ->orWhere('error_code', GenerationSync::SUBMISSION_FAILED))
            ->get();

        foreach ($stale as $generation) {
            try {
                $this->sync->apply($this->client->generation($generation->pixelforge_id));
            } catch (PixelforgeException $exception) {
                Log::warning('Could not reconcile generation.', ['generation' => $generation->id, 'error' => $exception->errorCode]);
            }
        }

        return $stale->count();
    }

    private function recoverGivenUpSubmissions(): void
    {
        $givenUp = Generation::query()
            ->where('error_code', GenerationSync::SUBMISSION_FAILED)
            ->whereNull('pixelforge_id')
            ->where('completed_at', '>=', now()->subMinutes(self::RECOVERY_WINDOW_MINUTES))
            ->get()
            ->keyBy('id');

        if ($givenUp->isEmpty()) {
            return;
        }

        try {
            $this->applyListedGenerations($givenUp, CarbonImmutable::parse($givenUp->min('created_at')));
        } catch (PixelforgeException $exception) {
            Log::warning('Could not look for given-up generations.', ['error' => $exception->errorCode]);
        }
    }

    /**
     * @param  Collection<string, Generation>  $givenUp
     */
    private function applyListedGenerations(Collection $givenUp, CarbonImmutable $since): void
    {
        $cursor = null;

        do {
            $page = $this->client->generations($cursor);

            foreach ($page['data'] as $remote) {
                if (CarbonImmutable::parse($remote['created_at'])->lt($since)) {
                    return;
                }

                if ($givenUp->has($remote['metadata']['generation_id'] ?? '')) {
                    $this->sync->apply($remote);
                }
            }

            $cursor = $page['next_cursor'];
        } while ($page['has_more']);
    }

    private function abandonStuckSubmissions(): void
    {
        Generation::query()
            ->where('status', GenerationStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(SubmitGeneration::RETRY_FOR_MINUTES + self::SUBMISSION_GRACE_MINUTES))
            ->get()
            ->each(fn (Generation $generation) => $this->sync->abandonSubmission($generation));
    }
}
