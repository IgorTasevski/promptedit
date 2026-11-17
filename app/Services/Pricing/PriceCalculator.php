<?php

namespace App\Services\Pricing;

use App\Enums\Quality;
use App\Enums\Size;
use App\Support\Money;

class PriceCalculator
{
    private const int MARKUP_PERCENT = 25;

    private const int TOKENS_PER_RATE_UNIT = 1_000_000;

    /**
     * @param  array<string, int>  $tokens
     * @param  array<string, string>  $rates
     */
    public function providerCost(array $tokens, array $rates): int
    {
        $total = 0;

        foreach ($rates as $type => $rate) {
            $total += ($tokens[$type] ?? 0) * Money::fromDecimal($rate);
        }

        return $this->divideRoundingUp($total, self::TOKENS_PER_RATE_UNIT);
    }

    /**
     * @param  array<string, int>  $tokens
     * @param  array<string, string>  $rates
     * @return array{lines: list<array{type: string, tokens: int, rate: string, cost: int}>, provider_cost: int, markup: int, waived: int, price: int}
     */
    public function breakdown(array $tokens, array $rates, int $providerCost, int $price): array
    {
        $lines = [];

        foreach ($rates as $type => $rate) {
            $lines[] = [
                'type' => $type,
                'tokens' => $tokens[$type] ?? 0,
                'rate' => $rate,
                'cost' => $this->providerCost([$type => $tokens[$type] ?? 0], [$type => $rate]),
            ];
        }

        $listPrice = $this->userPrice($providerCost);

        return [
            'lines' => $lines,
            'provider_cost' => $providerCost,
            'markup' => $listPrice - $providerCost,
            'waived' => $listPrice - $price,
            'price' => $price,
        ];
    }

    public function userPrice(int $providerCost): int
    {
        return $this->divideRoundingUp($providerCost * (100 + self::MARKUP_PERCENT), 100);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @return array{min: int, max: int}
     */
    public function estimate(array $pricing, string $prompt, Size $size, Quality $quality, int $referenceImages): array
    {
        $tokens = $pricing['tokens'];
        $textTokens = $this->divideRoundingUp(mb_strlen($prompt), $tokens['characters_per_text_token']);
        $variancePercent = (int) round($tokens['text_token_variance'] * 100);

        $fixed = [
            'input_image_tokens' => $referenceImages * $tokens['per_reference_image'],
            'output_tokens' => $tokens['output'][$quality->value][$size->value],
        ];

        $min = $fixed + [
            'input_text_tokens' => intdiv($textTokens * (100 - $variancePercent), 100),
            'reasoning_tokens' => $tokens['reasoning'][$quality->value]['min'],
        ];

        $max = $fixed + [
            'input_text_tokens' => $this->divideRoundingUp($textTokens * (100 + $variancePercent), 100),
            'reasoning_tokens' => $tokens['reasoning'][$quality->value]['max'],
        ];

        return [
            'min' => $this->userPrice($this->providerCost($min, $pricing['rates'])),
            'max' => $this->userPrice($this->providerCost($max, $pricing['rates'])),
        ];
    }

    private function divideRoundingUp(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }
}
