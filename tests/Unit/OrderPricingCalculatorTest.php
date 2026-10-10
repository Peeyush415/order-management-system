<?php

namespace Tests\Unit;

use App\Enums\DiscountType;
use App\Services\OrderPricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderPricingCalculatorTest extends TestCase
{
    private OrderPricingCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new OrderPricingCalculator;
    }

    private function line(int $unitPrice, int $quantity, int $taxRateBps = 1000): array
    {
        return ['unit_price' => $unitPrice, 'quantity' => $quantity, 'tax_rate_bps' => $taxRateBps];
    }

    public function test_assessment_example_totals_36300_cents(): void
    {
        // Product A: 2 × $100, Product B: 3 × $50, $20 discount, 10% tax.
        $result = $this->calculator->calculate(
            [$this->line(10000, 2), $this->line(5000, 3)],
            DiscountType::Fixed,
            2000,
        );

        $this->assertSame(35000, $result['subtotal']);
        $this->assertSame(2000, $result['discount_total']);
        $this->assertSame(3300, $result['tax_total']);
        $this->assertSame(36300, $result['grand_total']);

        // 2000 × 20000/35000 = 1142.86 → 1142, and 857.14 → 857; the leftover cent goes to the larger line.
        $this->assertSame([1143, 857], array_column($result['lines'], 'discount_amount'));
        $this->assertSame([1886, 1414], array_column($result['lines'], 'tax_amount'));
        $this->assertSame([20743, 15557], array_column($result['lines'], 'line_total'));
    }

    public function test_no_discount(): void
    {
        $result = $this->calculator->calculate([$this->line(999, 3, 1800)], null);

        $this->assertSame(2997, $result['subtotal']);
        $this->assertSame(0, $result['discount_total']);
        $this->assertSame(539, $result['tax_total']); // 539.46 → 539
        $this->assertSame(3536, $result['grand_total']);
    }

    public function test_percent_discount_rounds_half_up(): void
    {
        // 15% of 1250 = 187.5 → 188.
        $result = $this->calculator->calculate([$this->line(1250, 1, 0)], DiscountType::Percent, 1500);

        $this->assertSame(188, $result['discount_total']);
        $this->assertSame(1062, $result['grand_total']);
    }

    public function test_fixed_discount_never_exceeds_the_subtotal(): void
    {
        $result = $this->calculator->calculate([$this->line(500, 2)], DiscountType::Fixed, 5000);

        $this->assertSame(1000, $result['discount_total']);
        $this->assertSame(0, $result['tax_total']);
        $this->assertSame(0, $result['grand_total']);
    }

    public function test_leftover_cents_go_to_the_largest_line(): void
    {
        $result = $this->calculator->calculate(
            [$this->line(1000, 1, 0), $this->line(3000, 1, 0), $this->line(1000, 1, 0)],
            DiscountType::Fixed,
            100,
        );

        // 20 + 60 + 20 = 100 exactly; with 101 the extra cent must land on the 3000 line.
        $this->assertSame([20, 60, 20], array_column($result['lines'], 'discount_amount'));

        $result = $this->calculator->calculate(
            [$this->line(1000, 1, 0), $this->line(3000, 1, 0), $this->line(1000, 1, 0)],
            DiscountType::Fixed,
            101,
        );
        $this->assertSame([20, 61, 20], array_column($result['lines'], 'discount_amount'));
    }

    public function test_each_line_uses_its_own_tax_rate(): void
    {
        $result = $this->calculator->calculate([$this->line(10000, 1, 0), $this->line(10000, 1, 1800)], null);

        $this->assertSame([0, 1800], array_column($result['lines'], 'tax_amount'));
        $this->assertSame(21800, $result['grand_total']);
    }

    public function test_extra_line_keys_are_kept(): void
    {
        $result = $this->calculator->calculate(['a' => $this->line(100, 1) + ['product_id' => 7, 'sku' => 'KB-1']], null);

        $this->assertSame(7, $result['lines']['a']['product_id']);
        $this->assertSame('KB-1', $result['lines']['a']['sku']);
        $this->assertSame(110, $result['lines']['a']['line_total']);
    }

    public function test_totals_always_add_up_across_many_random_orders(): void
    {
        mt_srand(2026);

        for ($i = 0; $i < 500; $i++) {
            $lines = [];
            for ($n = mt_rand(1, 6); $n > 0; $n--) {
                $lines[] = $this->line(mt_rand(1, 100000), mt_rand(1, 20), [0, 500, 1000, 1800][mt_rand(0, 3)]);
            }
            $type = [null, DiscountType::Fixed, DiscountType::Percent][mt_rand(0, 2)];
            $value = match ($type) {
                DiscountType::Fixed => mt_rand(0, 500000),
                DiscountType::Percent => mt_rand(0, 10000),
                null => 0,
            };

            $result = $this->calculator->calculate($lines, $type, $value);
            $resultLines = $result['lines'];

            $this->assertSame($result['subtotal'], array_sum(array_column($resultLines, 'line_subtotal')));
            $this->assertSame($result['discount_total'], array_sum(array_column($resultLines, 'discount_amount')));
            $this->assertSame($result['tax_total'], array_sum(array_column($resultLines, 'tax_amount')));
            $this->assertSame($result['grand_total'], array_sum(array_column($resultLines, 'line_total')));
            $this->assertLessThanOrEqual($result['subtotal'], $result['discount_total']);
            foreach ($resultLines as $line) {
                $this->assertGreaterThanOrEqual(0, $line['line_subtotal'] - $line['discount_amount'], 'A line discount never exceeds its line');
            }
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'no lines' => [[], null, 0],
            'zero quantity' => [[['unit_price' => 100, 'quantity' => 0, 'tax_rate_bps' => 0]], null, 0],
            'negative price' => [[['unit_price' => -1, 'quantity' => 1, 'tax_rate_bps' => 0]], null, 0],
            'percent above 100' => [[['unit_price' => 100, 'quantity' => 1, 'tax_rate_bps' => 0]], DiscountType::Percent, 10001],
            'negative discount' => [[['unit_price' => 100, 'quantity' => 1, 'tax_rate_bps' => 0]], DiscountType::Fixed, -5],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_rejects_invalid_input(array $lines, ?DiscountType $type, int $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->calculate($lines, $type, $value);
    }
}
