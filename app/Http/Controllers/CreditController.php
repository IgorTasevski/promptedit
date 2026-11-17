<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseCreditsRequest;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CreditController extends Controller
{
    public function __construct(private readonly CreditService $credits) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('credits/Index', [
            'balance' => $this->credits->balance($user),
            'held' => $this->credits->held($user),
            'available' => $this->credits->available($user),
            'packs' => CreditService::PACKS,
            'transactions' => $this->credits->recentTransactions($user),
        ]);
    }

    public function store(PurchaseCreditsRequest $request): RedirectResponse
    {
        $this->credits->purchase($request->user(), $request->integer('amount'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Added \${$request->integer('amount')} in credits."]);

        return to_route('credits.index');
    }
}
