<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShowGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_the_exact_cost_breakdown_of_a_generation(): void
    {
        $generation = Generation::factory()->submitted(GenerationStatus::Succeeded)->create([
            'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
            'input_text_tokens' => 41,
            'input_image_tokens' => 0,
            'output_tokens' => 3300,
            'reasoning_tokens' => 2950,
            'provider_cost' => 169_080_000,
            'price' => 211_350_000,
        ]);

        $this->actingAs($generation->user)
            ->get(route('generations.show', $generation))
            ->assertInertia(fn (Assert $page) => $page
                ->component('generations/Show')
                ->where('generation.id', $generation->id)
                ->where('breakdown.lines', [
                    ['type' => 'input_text_tokens', 'tokens' => 41, 'rate' => '5.00', 'cost' => 205_000],
                    ['type' => 'input_image_tokens', 'tokens' => 0, 'rate' => '10.00', 'cost' => 0],
                    ['type' => 'output_tokens', 'tokens' => 3300, 'rate' => '40.00', 'cost' => 132_000_000],
                    ['type' => 'reasoning_tokens', 'tokens' => 2950, 'rate' => '12.50', 'cost' => 36_875_000],
                ])
                ->where('breakdown.provider_cost', 169_080_000)
                ->where('breakdown.markup', 42_270_000)
                ->where('breakdown.waived', 0)
                ->where('breakdown.price', 211_350_000));
    }
}
