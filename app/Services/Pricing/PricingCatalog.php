<?php

namespace App\Services\Pricing;

use App\Services\Pixelforge\PixelforgeClient;
use Illuminate\Support\Facades\Cache;

class PricingCatalog
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        return Cache::remember('pixelforge.pricing', now()->addHour(), fn () => $this->client->pricing());
    }
}
