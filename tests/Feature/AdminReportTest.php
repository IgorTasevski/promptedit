<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\FakesPixelforge;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use FakesPixelforge, RefreshDatabase;

    public function test_non_admins_cannot_see_the_report(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.report'))
            ->assertForbidden();
    }

    public function test_admin_sees_spend_cost_and_profit_per_day_and_user_checked_against_pixelforge(): void
    {
        $ana = User::factory()->create(['email' => 'ana@example.com']);
        $marko = User::factory()->create(['email' => 'marko@example.com']);
        $this->succeeded($ana, '2026-11-16 12:00:00', provider: 169_080_000, price: 211_350_000);
        $this->succeeded($marko, '2026-11-17 23:59:59', provider: 100_000_000, price: 125_000_000);
        Generation::factory()->for($marko)->submitted(GenerationStatus::Failed)->create(['completed_at' => '2026-11-17 09:00:00']);

        $this->fakePixelforge(['*/v1/usage*' => Http::response(['object' => 'usage', 'data' => [
            ['date' => '2026-11-16', 'generations' => 1, 'cost_usd' => '0.169080000'],
            ['date' => '2026-11-17', 'generations' => 1, 'cost_usd' => '0.100000000'],
        ]])]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.report', ['start_date' => '2026-11-16', 'end_date' => '2026-11-17']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Report')
                ->where('days', [
                    ['date' => '2026-11-16', 'generations' => 1, 'charged' => 211_350_000, 'provider_cost' => 169_080_000, 'pixelforge_cost' => 169_080_000, 'profit' => 42_270_000],
                    ['date' => '2026-11-17', 'generations' => 1, 'charged' => 125_000_000, 'provider_cost' => 100_000_000, 'pixelforge_cost' => 100_000_000, 'profit' => 25_000_000],
                ])
                ->where('users', [
                    ['email' => 'ana@example.com', 'succeeded' => 1, 'failed' => 0, 'charged' => 211_350_000, 'provider_cost' => 169_080_000, 'profit' => 42_270_000],
                    ['email' => 'marko@example.com', 'succeeded' => 1, 'failed' => 1, 'charged' => 125_000_000, 'provider_cost' => 100_000_000, 'profit' => 25_000_000],
                ]));
    }

    private function succeeded(User $user, string $completedAt, int $provider, int $price): void
    {
        Generation::factory()->for($user)->submitted(GenerationStatus::Succeeded)->create([
            'completed_at' => $completedAt,
            'provider_cost' => $provider,
            'price' => $price,
        ]);
    }
}
