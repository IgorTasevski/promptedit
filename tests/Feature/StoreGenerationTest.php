<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class StoreGenerationTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    private array $payload = [
        'prompt' => 'A lighthouse at dusk',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_images' => [],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakePixelforge();
    }

    public function test_estimate_returns_the_price_range_in_nanodollars(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('generations.estimate'), $this->payload)
            ->assertOk()
            ->assertExactJson(['min' => 58_150_000, 'max' => 86_287_500]);
    }

    public function test_user_with_enough_credits_queues_a_generation_holding_its_estimate(): void
    {
        $user = User::factory()->withCredits(1_000_000_000)->create();

        $this->actingAs($user)
            ->post(route('generations.store'), $this->payload)
            ->assertRedirect(route('generations.index'));

        $generation = $user->generations()->sole();
        $this->assertSame(GenerationStatus::Pending, $generation->status);
        $this->assertSame(58_150_000, $generation->estimated_min);
        $this->assertSame(86_287_500, $generation->estimated_max);
        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
    }

    public function test_user_who_cannot_afford_the_maximum_is_blocked(): void
    {
        $user = User::factory()->withCredits(86_287_499)->create();

        $this->actingAs($user)
            ->post(route('generations.store'), $this->payload)
            ->assertSessionHasErrors('credits');

        $this->assertSame(0, $user->generations()->count());
        Queue::assertNothingPushed();
    }
}
