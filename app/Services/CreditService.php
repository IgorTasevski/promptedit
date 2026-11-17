<?php

namespace App\Services;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;

class CreditService
{
    public const array PACKS = [5, 10, 25];

    private const int LEDGER_LIMIT = 50;

    public function balance(User $user): int
    {
        return (int) $user->creditTransactions()->sum('amount');
    }

    public function held(User $user): int
    {
        return (int) $user->generations()->inFlight()->sum('estimated_max');
    }

    public function available(User $user): int
    {
        return $this->balance($user) - $this->held($user);
    }

    /**
     * @return Collection<int, CreditTransaction>
     */
    public function recentTransactions(User $user): Collection
    {
        return $user->creditTransactions()->with('generation:id,prompt')->latest('id')->limit(self::LEDGER_LIMIT)->get();
    }

    public function purchase(User $user, int $dollars): CreditTransaction
    {
        return $user->creditTransactions()->create([
            'type' => CreditTransactionType::Purchase,
            'amount' => Money::fromDecimal((string) $dollars),
        ]);
    }
}
