<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_balance_held_credits_and_ledger(): void
    {
        $user = User::factory()->withCredits(1_000_000_000)->create();
        Generation::factory()->for($user)->submitted()->create(['estimated_max' => 86_287_500]);

        $this->actingAs($user)
            ->get(route('credits.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('credits/Index')
                ->where('balance', 1_000_000_000)
                ->where('held', 86_287_500)
                ->where('available', 913_712_500)
                ->has('transactions', 1));
    }

    public function test_buying_a_pack_adds_its_credits(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('credits.store'), ['amount' => 10])
            ->assertRedirect(route('credits.index'));

        $this->assertSame(10_000_000_000, app(CreditService::class)->balance($user));
    }
}
