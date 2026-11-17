<?php

namespace App\Services;

use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;

class WebhookProcessor
{
    public function __construct(private readonly GenerationSync $sync) {}

    /**
     * @param  array<string, mixed>|null  $generation
     */
    public function process(string $eventId, string $type, ?array $generation): void
    {
        DB::transaction(function () use ($eventId, $type, $generation) {
            $recorded = WebhookEvent::query()->insertOrIgnore([
                'id' => $eventId,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($recorded === 0 || $generation === null) {
                return;
            }

            $this->sync->apply($generation);
        });
    }
}
