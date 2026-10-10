import { describe, expect, it } from 'vitest';
import { calculatePricing } from '@/composables/useOrderPricing';

const line = (unit_price, quantity, tax_rate_bps = 1000) => ({ unit_price, quantity, tax_rate_bps });

// Same cases as the backend's OrderPricingCalculatorTest, so preview and server agree.
describe('order pricing preview', () => {
    it('matches the assessment example: 36300 cents', () => {
        const result = calculatePricing([line(10000, 2), line(5000, 3)], 'fixed', 2000);

        expect(result).toMatchObject({ subtotal: 35000, discount_total: 2000, tax_total: 3300, grand_total: 36300 });
        expect(result.lines.map((l) => l.discount_amount)).toEqual([1143, 857]);
        expect(result.lines.map((l) => l.tax_amount)).toEqual([1886, 1414]);
        expect(result.lines.map((l) => l.line_total)).toEqual([20743, 15557]);
    });

    it('rounds a percent discount half up', () => {
        // 15% of 1250 = 187.5 → 188
        expect(calculatePricing([line(1250, 1, 0)], 'percent', 1500)).toMatchObject({ discount_total: 188, grand_total: 1062 });
    });

    it('caps a fixed discount at the subtotal', () => {
        expect(calculatePricing([line(500, 2)], 'fixed', 5000)).toMatchObject({ discount_total: 1000, tax_total: 0, grand_total: 0 });
    });

    it('gives leftover cents to the largest line', () => {
        const result = calculatePricing([line(1000, 1, 0), line(3000, 1, 0), line(1000, 1, 0)], 'fixed', 101);

        expect(result.lines.map((l) => l.discount_amount)).toEqual([20, 61, 20]);
    });

    it('handles no discount, no lines and mixed tax rates', () => {
        expect(calculatePricing([line(999, 3, 1800)], '', 0)).toMatchObject({ subtotal: 2997, tax_total: 539, grand_total: 3536 });
        expect(calculatePricing([], 'fixed', 500)).toMatchObject({ subtotal: 0, discount_total: 0, grand_total: 0 });
        expect(calculatePricing([line(10000, 1, 0), line(10000, 1, 1800)], null).tax_total).toBe(1800);
    });

    it('line amounts always add up to the totals', () => {
        let seed = 7;
        const random = (max) => (seed = (seed * 16807) % 2147483647) % max;

        for (let i = 0; i < 300; i++) {
            const lines = Array.from({ length: 1 + random(6) }, () => line(1 + random(100000), 1 + random(20), [0, 500, 1000, 1800][random(4)]));
            const type = [null, 'fixed', 'percent'][random(3)];
            const r = calculatePricing(lines, type, type === 'fixed' ? random(500000) : random(10001));
            const sum = (key) => r.lines.reduce((s, l) => s + l[key], 0);

            expect(sum('discount_amount')).toBe(r.discount_total);
            expect(sum('tax_amount')).toBe(r.tax_total);
            expect(sum('line_total')).toBe(r.grand_total);
        }
    });
});
