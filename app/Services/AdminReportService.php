<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Services\Pixelforge\PixelforgeClient;
use App\Services\Pixelforge\PixelforgeException;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AdminReportService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /**
     * @return array{days: list<array<string, int|string|null>>, users: list<array<string, int|string>>, usage_error: string|null}
     */
    public function build(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $completed = Generation::query()
            ->with('user:id,email')
            ->whereIn('status', [GenerationStatus::Succeeded, GenerationStatus::Failed])
            ->whereBetween('completed_at', [$start, $end])
            ->get(['id', 'user_id', 'status', 'provider_cost', 'price', 'completed_at']);

        $succeeded = $completed->where('status', GenerationStatus::Succeeded);

        try {
            $pixelforgeCosts = $this->pixelforgeCosts($start, $end);
            $usageError = null;
        } catch (PixelforgeException $exception) {
            $pixelforgeCosts = collect();
            $usageError = $exception->getMessage();
        }

        return [
            'days' => $this->days($succeeded, $pixelforgeCosts, $start, $end),
            'users' => $this->users($completed),
            'usage_error' => $usageError,
        ];
    }

    /**
     * @param  Collection<int, Generation>  $succeeded
     * @param  Collection<string, int>  $pixelforgeCosts
     * @return list<array<string, int|string|null>>
     */
    private function days(Collection $succeeded, Collection $pixelforgeCosts, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byDay = $succeeded->groupBy(fn (Generation $generation) => $generation->completed_at->toDateString());

        return array_values(collect(CarbonPeriod::create($start->startOfDay(), $end->startOfDay())->toArray())
            ->map(function ($day) use ($byDay, $pixelforgeCosts) {
                $date = $day->toDateString();
                $generations = $byDay->get($date, collect());
                $totals = $this->totals($generations);

                return [
                    'date' => $date,
                    'generations' => $generations->count(),
                    'charged' => $totals['charged'],
                    'provider_cost' => $totals['provider_cost'],
                    'pixelforge_cost' => $pixelforgeCosts->get($date),
                    'profit' => $totals['profit'],
                ];
            })
            ->all());
    }

    /**
     * @param  Collection<int, Generation>  $completed
     * @return list<array<string, int|string>>
     */
    private function users(Collection $completed): array
    {
        return array_values($completed
            ->groupBy('user_id')
            ->map(function (Collection $generations) {
                $succeeded = $generations->where('status', GenerationStatus::Succeeded);

                return [
                    'email' => $generations->first()->user->email,
                    'succeeded' => $succeeded->count(),
                    'failed' => $generations->count() - $succeeded->count(),
                    ...$this->totals($succeeded),
                ];
            })
            ->sortByDesc('charged')
            ->all());
    }

    /**
     * @param  Collection<int, Generation>  $generations
     * @return array{charged: int, provider_cost: int, profit: int}
     */
    private function totals(Collection $generations): array
    {
        $charged = (int) $generations->sum('price');
        $providerCost = (int) $generations->sum('provider_cost');

        return ['charged' => $charged, 'provider_cost' => $providerCost, 'profit' => $charged - $providerCost];
    }

    /**
     * @return Collection<string, int>
     */
    private function pixelforgeCosts(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $costs = collect();

        foreach ($this->client->usage($start->toDateString(), $end->toDateString())['data'] as $day) {
            $costs->put($day['date'], Money::fromDecimal($day['cost_usd']));
        }

        return $costs;
    }
}
