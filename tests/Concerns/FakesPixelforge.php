<?php

namespace Tests\Concerns;

use App\Models\Generation;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

trait FakesPixelforge
{
    protected function fakePixelforge(array $responses = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            ...$responses,
            '*/v1/pricing' => Http::response($this->pixelforgeFixture('pricing')),
        ]);
    }

    protected function pixelforgeGeneration(Generation $generation, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'gen_'.substr($generation->id, 0, 8),
            'object' => 'generation',
            'status' => 'queued',
            'prompt' => $generation->prompt,
            'size' => $generation->size->value,
            'quality' => $generation->quality->value,
            'reference_images' => $generation->reference_images,
            'metadata' => ['generation_id' => $generation->id],
            'image_url' => null,
            'image_url_expires_at' => null,
            'usage' => null,
            'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
            'error' => null,
            'created_at' => '2026-11-17T10:00:00Z',
            'started_at' => null,
            'completed_at' => null,
        ], $overrides);
    }

    protected function pixelforgeError(string $code, int $status, array $headers = []): PromiseInterface
    {
        return Http::response(['error' => ['type' => 'error', 'code' => $code, 'message' => "Pixelforge says {$code}."]], $status, $headers);
    }

    protected function pixelforgeFixture(string $name): array
    {
        return json_decode(file_get_contents(base_path("tests/Fixtures/pixelforge/{$name}.json")), true);
    }
}
