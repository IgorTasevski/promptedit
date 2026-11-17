<?php

namespace Tests\Unit;

use App\Enums\Quality;
use App\Enums\Size;
use App\Services\Pricing\PriceCalculator;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    private array $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PriceCalculator;
        $this->pricing = json_decode(file_get_contents(__DIR__.'/../Fixtures/pixelforge/pricing.json'), true);
    }

    public function test_provider_cost_matches_the_documented_usage_example(): void
    {
        $cost = $this->calculator->providerCost(
            ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950],
            $this->pricing['rates'],
        );

        $this->assertSame(169_080_000, $cost);
    }

    public function test_user_price_adds_a_quarter_and_rounds_up_to_the_nanodollar(): void
    {
        $this->assertSame(211_350_000, $this->calculator->userPrice(169_080_000));
        $this->assertSame(2, $this->calculator->userPrice(1));
    }

    public function test_estimate_matches_the_documented_worst_case(): void
    {
        $estimate = $this->calculator->estimate($this->pricing, str_repeat('a', 400), Size::Landscape, Quality::High, 2);

        $this->assertSame(['min' => 357_781_250, 'max' => 467_343_750], $estimate);
    }
}
