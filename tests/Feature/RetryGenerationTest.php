<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class RetryGenerationTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_owner_retries_a_failed_generation_with_the_same_settings(): void
    {
        Queue::fake();
        $this->fakePixelforge();
        $user = User::factory()->withCredits(1_000_000_000)->create();
        $failed = Generation::factory()->for($user)->submitted(GenerationStatus::Failed)->create([
            'reference_images' => ['https://images.example.com/ref.jpg'],
            'error_code' => 'internal_error',
        ]);

        $response = $this->actingAs($user)->post(route('generations.retry', $failed));

        $retry = $user->generations()->whereKeyNot($failed->id)->sole();
        $response->assertRedirect(route('generations.show', $retry));
        $this->assertSame(GenerationStatus::Pending, $retry->status);
        $this->assertSame($failed->prompt, $retry->prompt);
        $this->assertSame($failed->size, $retry->size);
        $this->assertSame($failed->quality, $retry->quality);
        $this->assertSame(['https://images.example.com/ref.jpg'], $retry->reference_images);
        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($retry));
    }
}
