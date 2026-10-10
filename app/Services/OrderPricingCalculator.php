<?php

namespace App\Services;

use App\Enums\DiscountType;
use InvalidArgumentException;

/**
 * The single source of truth for order money. Pure: no database, no facades, integer cents only.
 *
 *   line_subtotal = quantity × unit_price
 *   subtotal      = Σ line_subtotal
 *   discount      = fixed ? min(value, subtotal) : round_half_up(subtotal × bps / 10000)
 *   line discount = floor(discount × line_subtotal / subtotal); leftover cents go to the largest line
 *   line tax      = round_half_up((line_subtotal − line discount) × tax_rate_bps / 10000)
 *   grand_total   = subtotal − discount + Σ line tax
 *
 * Example from the assessment: 2 × 10000 + 3 × 5000, 2000 off, 10% tax → 36300.
 */
class OrderPricingCalculator
{
    private const BPS = 10000;

    /**
     * @param  array<int|string, array{unit_price: int, quantity: int, tax_rate_bps: int}>  $lines  extra keys are kept
     * @param  int  $discountValue  cents for a fixed discount, basis points for a percent discount
     * @return array{subtotal: int, discount_total: int, tax_total: int, grand_total: int, lines: array<int|string, array<string, mixed>>}
     *
     * @throws InvalidArgumentException on input the request validation should already have rejected
     */
    public function calculate(array $lines, ?DiscountType $discountType, int $discountValue = 0): array
    {
        $this->guard($lines, $discountType, $discountValue);

        $subtotals = array_map(fn (array $line) => $line['quantity'] * $line['unit_price'], $lines);
        $subtotal = array_sum($subtotals);

        $discountTotal = match ($discountType) {
            DiscountType::Fixed => min($discountValue, $subtotal),
            DiscountType::Percent => $this->roundHalfUp($subtotal * $discountValue, self::BPS),
            null => 0,
        };

        $discounts = $this->prorate($discountTotal, $subtotals, $subtotal);

        $taxTotal = 0;
        foreach ($lines as $key => $line) {
            $tax = $this->roundHalfUp(($subtotals[$key] - $discounts[$key]) * $line['tax_rate_bps'], self::BPS);
            $taxTotal += $tax;

            $lines[$key] += [
                'line_subtotal' => $subtotals[$key],
                'discount_amount' => $discounts[$key],
                'tax_amount' => $tax,
                'line_total' => $subtotals[$key] - $discounts[$key] + $tax,
            ];
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal - $discountTotal + $taxTotal,
            'lines' => $lines,
        ];
    }

    /**
     * Splits the order discount across lines by their share of the subtotal. Flooring loses
     * a few cents; they go to the largest line so the line discounts add up exactly.
     *
     * @param  array<int|string, int>  $subtotals
     * @return array<int|string, int>
     */
    private function prorate(int $discountTotal, array $subtotals, int $subtotal): array
    {
        if ($discountTotal === 0 || $subtotal === 0) {
            return array_map(fn () => 0, $subtotals);
        }

        $discounts = array_map(fn (int $lineSubtotal) => intdiv($discountTotal * $lineSubtotal, $subtotal), $subtotals);
        $largest = array_search(max($subtotals), $subtotals, true);
        $discounts[$largest] += $discountTotal - array_sum($discounts);

        return $discounts;
    }

    /** Integer division rounding .5 up, for non-negative values. */
    private function roundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv($numerator * 2 + $denominator, $denominator * 2);
    }

    private function guard(array $lines, ?DiscountType $discountType, int $discountValue): void
    {
        if ($lines === []) {
            throw new InvalidArgumentException('An order needs at least one line.');
        }

        foreach ($lines as $line) {
            if (($line['quantity'] ?? 0) < 1) {
                throw new InvalidArgumentException('Quantity must be at least 1.');
            }
            if (($line['unit_price'] ?? -1) < 0 || ($line['tax_rate_bps'] ?? -1) < 0) {
                throw new InvalidArgumentException('Prices and tax rates cannot be negative.');
            }
        }

        if ($discountValue < 0 || ($discountType === DiscountType::Percent && $discountValue > self::BPS)) {
            throw new InvalidArgumentException('Discount must be between 0 and 100%.');
        }
    }
}
