<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class ReconcileGenerationsTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_a_generation_whose_webhook_never_arrived_is_settled_by_polling(): void
    {
        $user = User::factory()->withCredits(1_000_000_000)->create();
        $generation = Generation::factory()->for($user)->submitted(GenerationStatus::Processing)->create([
            'estimated_max' => 250_000_000,
            'updated_at' => now()->subMinutes(3),
        ]);

        $this->fakePixelforge([
            "*/v1/generations/{$generation->pixelforge_id}" => Http::response($this->pixelforgeGeneration($generation, [
                'id' => $generation->pixelforge_id,
                'status' => 'succeeded',
                'usage' => ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291],
                'image_url' => 'https://pixelforge.promptedit.dev/images/gen.png',
                'image_url_expires_at' => '2026-11-18T10:00:20Z',
                'completed_at' => '2026-11-17T10:00:20Z',
            ])),
        ]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(211_350_000, $generation->price);
    }

    public function test_a_submission_whose_job_was_lost_is_released_for_free(): void
    {
        $generation = Generation::factory()->create(['created_at' => now()->subMinutes(16)]);
        $this->fakePixelforge(['*/v1/generations?*' => $this->pixelforgeList([])]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('submission_failed', $generation->error_code);
    }

    public function test_an_unreachable_pixelforge_does_not_stop_the_other_generations(): void
    {
        $unreachable = Generation::factory()->submitted(GenerationStatus::Processing)->create(['updated_at' => now()->subMinutes(3)]);
        $generation = Generation::factory()->submitted(GenerationStatus::Queued)->create(['updated_at' => now()->subMinutes(3)]);

        $this->fakePixelforge([
            "*/v1/generations/{$unreachable->pixelforge_id}" => Http::failedConnection(),
            "*/v1/generations/{$generation->pixelforge_id}" => Http::response($this->pixelforgeGeneration($generation, [
                'id' => $generation->pixelforge_id,
                'status' => 'processing',
            ])),
        ]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $this->assertSame(GenerationStatus::Processing, $generation->refresh()->status);
    }

    public function test_a_given_up_generation_that_pixelforge_completed_is_settled_for_free(): void
    {
        $generation = Generation::factory()->submitted(GenerationStatus::Failed)->create([
            'error_code' => 'submission_failed',
            'updated_at' => now()->subMinutes(3),
        ]);

        $this->fakePixelforge([
            "*/v1/generations/{$generation->pixelforge_id}" => Http::response($this->pixelforgeGeneration($generation, [
                'id' => $generation->pixelforge_id,
                'status' => 'succeeded',
                'usage' => ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291],
                'image_url' => 'https://pixelforge.promptedit.dev/images/gen.png',
                'image_url_expires_at' => '2026-11-18T10:00:20Z',
                'completed_at' => '2026-11-17T10:00:20Z',
            ])),
        ]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame(0, $generation->price);
    }

    public function test_a_given_up_generation_pixelforge_did_create_is_found_and_delivered_free(): void
    {
        $generation = $this->givenUpGeneration();
        $remote = $this->pixelforgeGeneration($generation, [
            'id' => 'gen_found',
            'status' => 'succeeded',
            'usage' => ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291],
            'image_url' => 'https://pixelforge.promptedit.dev/images/gen.png',
            'image_url_expires_at' => '2026-11-18T10:00:20Z',
            'created_at' => now()->subMinutes(10)->toIso8601String(),
            'completed_at' => now()->subMinutes(9)->toIso8601String(),
        ]);
        $this->fakePixelforge(['*/v1/generations?*' => $this->pixelforgeList([$remote])]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('gen_found', $generation->pixelforge_id);
        $this->assertSame(0, $generation->price);
    }

    public function test_a_given_up_generation_pixelforge_never_created_stays_failed(): void
    {
        $generation = $this->givenUpGeneration();
        $older = Generation::factory()->make();
        $this->fakePixelforge(['*/v1/generations?*' => $this->pixelforgeList([
            $this->pixelforgeGeneration($older, ['created_at' => now()->subHour()->toIso8601String()]),
        ], hasMore: true)]);

        $this->artisan('app:reconcile-generations')->assertSuccessful();

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        Http::assertSentCount(1);
    }

    private function givenUpGeneration(): Generation
    {
        return Generation::factory()->create([
            'status' => GenerationStatus::Failed,
            'error_code' => 'submission_failed',
            'created_at' => now()->subMinutes(12),
            'completed_at' => now()->subMinutes(2),
        ]);
    }

    private function pixelforgeList(array $generations, bool $hasMore = false): PromiseInterface
    {
        return Http::response(['object' => 'list', 'data' => $generations, 'has_more' => $hasMore, 'next_cursor' => $hasMore ? 'cur_next' : null]);
    }
}
