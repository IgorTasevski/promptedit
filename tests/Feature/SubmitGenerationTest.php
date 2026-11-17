<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_accepted_generation_is_queued_with_its_pixelforge_id(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::response($this->pixelforgeGeneration($generation, ['id' => 'gen_abc']), 202)]);

        app()->call([new SubmitGeneration($generation), 'handle']);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame('gen_abc', $generation->pixelforge_id);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->id)
            && $request['metadata'] === ['generation_id' => $generation->id]
            && array_keys($request->data()) === ['prompt', 'size', 'quality', 'reference_images', 'metadata']);
    }

    public function test_overloaded_pixelforge_releases_the_job_after_retry_after(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => $this->pixelforgeError('overloaded', 503, ['Retry-After' => '2'])]);

        $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $job->assertReleased(delay: 2);
        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
        $this->assertSame(1, $generation->submission_attempts);
    }

    public function test_rate_limit_without_retry_after_backs_off_exponentially(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => $this->pixelforgeError('concurrency_limit_exceeded', 429)]);

        $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
        $job->job->attempts = 4;
        app()->call([$job, 'handle']);

        $job->assertReleased(delay: 16);
    }

    public function test_unreachable_pixelforge_releases_the_job(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => Http::failedConnection()]);

        $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
        app()->call([$job, 'handle']);

        $job->assertReleased(delay: 2);
        $this->assertSame(GenerationStatus::Pending, $generation->refresh()->status);
    }

    public function test_daily_quota_fails_the_generation_with_a_clear_message(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => $this->pixelforgeError('daily_limit_exceeded', 429)]);

        app()->call([new SubmitGeneration($generation), 'handle']);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertStringContainsString('after midnight UTC', $generation->error_message);
    }

    public function test_content_policy_rejection_fails_the_generation_for_free(): void
    {
        $generation = Generation::factory()->create();
        $this->fakePixelforge(['*/v1/generations' => $this->pixelforgeError('content_policy_violation', 400)]);

        app()->call([new SubmitGeneration($generation), 'handle']);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertNull($generation->price);
        $this->assertSame(0, $generation->user->creditTransactions()->where('generation_id', $generation->id)->count());
    }

    public function test_generation_fails_for_free_when_retries_run_out(): void
    {
        $generation = Generation::factory()->create();

        (new SubmitGeneration($generation))->failed(new RuntimeException);

        $this->assertSame(GenerationStatus::Failed, $generation->refresh()->status);
        $this->assertSame('submission_failed', $generation->error_code);
    }
}
