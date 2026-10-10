import { computed, toValue } from 'vue';

/*
 * A copy of the backend's OrderPricingCalculator, used ONLY for the live preview in the
 * order form. The server recalculates every amount on save and ignores totals from the client.
 *
 *   line_subtotal = quantity × unit_price
 *   discount      = fixed: min(value, subtotal) | percent: round_half_up(subtotal × bps / 10000)
 *   line discount = floor(discount × line_subtotal / subtotal); leftover cents → largest line
 *   line tax      = round_half_up((line_subtotal − line discount) × tax_rate_bps / 10000)
 *   grand_total   = subtotal − discount + Σ tax
 *
 * Integer cents throughout; BigInt for the multiplications so large orders can't lose precision.
 */

const BPS = 10000n;

/** Integer division rounding .5 up, for non-negative BigInts. */
const roundHalfUp = (numerator, denominator) => (numerator * 2n + denominator) / (denominator * 2n);

/**
 * @param {{ unit_price: number, quantity: number, tax_rate_bps: number }[]} lines
 * @param {''|null|'fixed'|'percent'} discountType
 * @param {number} discountValue cents (fixed) or basis points (percent)
 */
export function calculatePricing(lines, discountType, discountValue = 0) {
    const subtotals = lines.map((line) => BigInt(Math.max(0, line.quantity | 0)) * BigInt(line.unit_price));
    const subtotal = subtotals.reduce((sum, value) => sum + value, 0n);
    const value = BigInt(Math.max(0, Math.trunc(discountValue || 0)));

    let discountTotal = 0n;
    if (discountType === 'fixed') {
        discountTotal = value < subtotal ? value : subtotal;
    } else if (discountType === 'percent') {
        discountTotal = roundHalfUp(subtotal * (value > BPS ? BPS : value), BPS);
    }

    const discounts = subtotals.map((lineSubtotal) => (subtotal > 0n ? (discountTotal * lineSubtotal) / subtotal : 0n));
    if (discountTotal > 0n && subtotals.length) {
        const largest = subtotals.reduce((best, v, i) => (v > subtotals[best] ? i : best), 0);
        discounts[largest] += discountTotal - discounts.reduce((sum, v) => sum + v, 0n);
    }

    let taxTotal = 0n;
    const resultLines = lines.map((line, i) => {
        const tax = roundHalfUp((subtotals[i] - discounts[i]) * BigInt(line.tax_rate_bps), BPS);
        taxTotal += tax;

        return {
            ...line,
            line_subtotal: Number(subtotals[i]),
            discount_amount: Number(discounts[i]),
            tax_amount: Number(tax),
            line_total: Number(subtotals[i] - discounts[i] + tax),
        };
    });

    return {
        subtotal: Number(subtotal),
        discount_total: Number(discountTotal),
        tax_total: Number(taxTotal),
        grand_total: Number(subtotal - discountTotal + taxTotal),
        lines: resultLines,
    };
}

/** Reactive preview: re-calculates whenever lines or the discount change. */
export function useOrderPricing(lines, discountType, discountValue) {
    return computed(() => calculatePricing(toValue(lines), toValue(discountType), toValue(discountValue)));
}
