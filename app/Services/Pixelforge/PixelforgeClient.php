<?php

namespace App\Services\Pixelforge;

use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    private const int PAGE_SIZE = 100;

    /**
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('pricing'));
    }

    /**
     * @return array<string, mixed>
     */
    public function createGeneration(Generation $generation): array
    {
        return $this->send(fn (PendingRequest $request) => $request
            ->withHeader('Idempotency-Key', $generation->id)
            ->post('generations', [
                'prompt' => $generation->prompt,
                'size' => $generation->size->value,
                'quality' => $generation->quality->value,
                'reference_images' => $generation->reference_images,
                'metadata' => ['generation_id' => $generation->id],
            ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function generation(string $pixelforgeId): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get("generations/{$pixelforgeId}"));
    }

    /**
     * @return array<string, mixed>
     */
    public function generations(?string $cursor = null): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('generations', array_filter([
            'limit' => self::PAGE_SIZE,
            'cursor' => $cursor,
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    public function usage(string $startDate, string $endDate): array
    {
        return $this->send(fn (PendingRequest $request) => $request->get('usage', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     */
    private function send(callable $call): array
    {
        $request = Http::baseUrl(config('services.pixelforge.url'))
            ->withToken(config('services.pixelforge.key'))
            ->acceptJson()
            ->timeout(15);

        try {
            return $call($request)->throw()->json();
        } catch (RequestException $exception) {
            throw PixelforgeException::fromResponse($exception->response);
        } catch (ConnectionException) {
            throw PixelforgeException::unreachable();
        }
    }
}
