<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\Quality;
use App\Enums\Size;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => GenerationStatus::Pending,
            'prompt' => 'A lighthouse at dusk',
            'size' => Size::Square,
            'quality' => Quality::Medium,
            'reference_images' => [],
            'estimated_min' => 58_150_000,
            'estimated_max' => 86_287_500,
        ];
    }

    public function submitted(GenerationStatus $status = GenerationStatus::Queued): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'pixelforge_id' => 'gen_'.fake()->unique()->bothify('########'),
        ]);
    }
}
