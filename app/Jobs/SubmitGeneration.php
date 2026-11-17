<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\GenerationSync;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    public const int RETRY_FOR_MINUTES = 10;

    private const int BASE_BACKOFF_SECONDS = 2;

    private const int MAX_BACKOFF_SECONDS = 60;

    public int $backoff = self::BASE_BACKOFF_SECONDS;

    public function __construct(public Generation $generation) {}

    public function retryUntil(): DateTimeInterface
    {
        return $this->generation->created_at->addMinutes(self::RETRY_FOR_MINUTES);
    }

    public function handle(PixelforgeClient $client, GenerationSync $sync): void
    {
        $this->generation->increment('submission_attempts');

        try {
            $remote = $client->createGeneration($this->generation);
        } catch (PixelforgeException $exception) {
            if ($exception->isRetryable()) {
                $this->release(max($exception->retryAfter ?? 0, $this->backoffSeconds()));

                return;
            }

            $sync->reject($this->generation, $exception->errorCode, $exception->userMessage());

            return;
        }

        $sync->apply($remote);
    }

    public function failed(?Throwable $exception): void
    {
        app(GenerationSync::class)->abandonSubmission($this->generation);
    }

    private function backoffSeconds(): int
    {
        return min(self::MAX_BACKOFF_SECONDS, self::BASE_BACKOFF_SECONDS * 2 ** ($this->attempts() - 1));
    }
}
