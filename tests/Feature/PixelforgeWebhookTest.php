<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    private Generation $generation;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->withCredits(1_000_000_000)->create();
        $this->generation = Generation::factory()->for($user)->submitted()->create(['estimated_max' => 250_000_000]);
    }

    public function test_succeeded_event_charges_the_exact_price_once(): void
    {
        $this->sendWebhook($this->succeededEvent('evt_1'))->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $this->generation->status);
        $this->assertSame(169_080_000, $this->generation->provider_cost);
        $this->assertSame(211_350_000, $this->generation->price);
        $this->assertSame(788_650_000, app(CreditService::class)->balance($this->generation->user));
    }

    public function test_the_same_event_delivered_twice_is_recorded_once(): void
    {
        $this->sendWebhook($this->succeededEvent('evt_1'))->assertNoContent();
        $this->sendWebhook($this->succeededEvent('evt_1'))->assertNoContent();

        $this->assertSame(1, $this->generation->user->creditTransactions()->where('generation_id', $this->generation->id)->count());
    }

    public function test_a_late_processing_event_never_moves_a_generation_back(): void
    {
        $this->sendWebhook($this->succeededEvent('evt_2'))->assertNoContent();
        $this->sendWebhook($this->event('evt_1', 'generation.processing', ['status' => 'processing']))->assertNoContent();

        $this->assertSame(GenerationStatus::Succeeded, $this->generation->refresh()->status);
    }

    public function test_failed_event_costs_nothing(): void
    {
        $this->sendWebhook($this->event('evt_1', 'generation.failed', [
            'status' => 'failed',
            'error' => ['code' => 'generation_failed', 'message' => 'The model could not produce an image.'],
            'completed_at' => '2026-11-17T10:00:20Z',
        ]))->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $this->generation->status);
        $this->assertNull($this->generation->price);
        $this->assertSame(1_000_000_000, app(CreditService::class)->balance($this->generation->user));
    }

    public function test_event_arriving_before_submission_returns_still_finds_the_generation(): void
    {
        $pending = Generation::factory()->for($this->generation->user)->create();

        $this->sendWebhook([
            'id' => 'evt_early',
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => '2026-11-17T10:00:02Z',
            'data' => ['object' => $this->pixelforgeGeneration($pending, ['id' => 'gen_early', 'status' => 'processing'])],
        ])->assertNoContent();

        $pending->refresh();
        $this->assertSame(GenerationStatus::Processing, $pending->status);
        $this->assertSame('gen_early', $pending->pixelforge_id);
    }

    public function test_ping_event_is_acknowledged(): void
    {
        $this->sendWebhook(['id' => 'evt_ping', 'object' => 'event', 'type' => 'ping', 'created_at' => '2026-11-17T10:00:00Z', 'data' => []])
            ->assertNoContent();
    }

    public function test_image_expiry_defaults_to_a_day_after_completion_when_missing(): void
    {
        $event = $this->succeededEvent('evt_1');
        unset($event['data']['object']['image_url_expires_at']);

        $this->sendWebhook($event)->assertNoContent();

        $this->assertSame('2026-11-18T10:00:20+00:00', $this->generation->refresh()->image_url_expires_at->toIso8601String());
    }

    public function test_success_after_submission_was_given_up_is_delivered_free(): void
    {
        $this->giveUpOnSubmission();

        $this->sendWebhook($this->succeededEvent('evt_1'))->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $this->generation->status);
        $this->assertSame(169_080_000, $this->generation->provider_cost);
        $this->assertSame(0, $this->generation->price);
        $this->assertNull($this->generation->error_code);
        $this->assertSame(1_000_000_000, app(CreditService::class)->balance($this->generation->user));
    }

    public function test_processing_event_after_submission_was_given_up_keeps_it_failed(): void
    {
        $this->giveUpOnSubmission();

        $this->sendWebhook($this->event('evt_1', 'generation.processing', ['id' => 'gen_late', 'status' => 'processing']))->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $this->generation->status);
        $this->assertSame('submission_failed', $this->generation->error_code);
        $this->assertSame('gen_late', $this->generation->pixelforge_id);
    }

    public function test_charge_never_exceeds_the_amount_held(): void
    {
        $this->generation->update(['estimated_max' => 200_000_000]);

        $this->sendWebhook($this->succeededEvent('evt_1'))->assertNoContent();

        $this->generation->refresh();
        $this->assertSame(169_080_000, $this->generation->provider_cost);
        $this->assertSame(200_000_000, $this->generation->price);
        $this->assertSame(800_000_000, app(CreditService::class)->balance($this->generation->user));
    }

    public function test_event_for_an_unknown_generation_is_logged(): void
    {
        Log::spy();
        $event = $this->succeededEvent('evt_1');
        $event['data']['object']['metadata'] = [];

        $this->sendWebhook($event)->assertNoContent();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context) => $context['pixelforge_id'] === $this->generation->pixelforge_id);
        $this->assertNull($this->generation->refresh()->price);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->sendWebhook($this->succeededEvent('evt_1'), secret: 'whsec_wrong')->assertBadRequest();
        $this->sendWebhook($this->succeededEvent('evt_2'), timestamp: time() - 301)->assertBadRequest();

        $this->assertNull($this->generation->refresh()->price);
    }

    private function giveUpOnSubmission(): void
    {
        $this->generation->update([
            'status' => GenerationStatus::Failed,
            'error_code' => 'submission_failed',
            'pixelforge_id' => null,
        ]);
    }

    private function succeededEvent(string $eventId): array
    {
        return $this->event($eventId, 'generation.succeeded', [
            'status' => 'succeeded',
            'image_url' => 'https://pixelforge.promptedit.dev/images/gen.png?sig=abc',
            'image_url_expires_at' => '2026-11-18T10:00:20Z',
            'usage' => ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950, 'total_tokens' => 6291],
            'started_at' => '2026-11-17T10:00:03Z',
            'completed_at' => '2026-11-17T10:00:20Z',
        ]);
    }

    private function event(string $eventId, string $type, array $overrides): array
    {
        return [
            'id' => $eventId,
            'object' => 'event',
            'type' => $type,
            'created_at' => '2026-11-17T10:00:20Z',
            'data' => ['object' => $this->pixelforgeGeneration($this->generation, ['id' => $this->generation->pixelforge_id, ...$overrides])],
        ];
    }

    private function sendWebhook(array $event, string $secret = 'whsec_test', ?int $timestamp = null): TestResponse
    {
        $body = json_encode($event);
        $timestamp ??= time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);

        return $this->call('POST', route('webhooks.pixelforge'), server: $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Pixelforge-Signature' => "t={$timestamp},v1={$signature}",
        ]), content: $body);
    }
}
