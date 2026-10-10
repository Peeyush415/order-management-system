import { describe, expect, it } from 'vitest';
import { centsToInput, formatBps, formatMoney, toCents } from '@/utils/money';

describe('money utils', () => {
    it.each([
        ['125.50', 12550],
        ['125.5', 12550],
        ['125', 12500],
        ['0.05', 5],
        ['1,250.99', 125099],
        // 0.1 + 0.2 style float errors can't happen: no floats involved.
        ['19.99', 1999],
    ])('toCents(%s) = %i', (input, cents) => {
        expect(toCents(input)).toBe(cents);
    });

    it.each(['', 'abc', '1.234', '-5', '12.3.4'])('toCents(%s) is null', (input) => {
        expect(toCents(input)).toBeNull();
    });

    it('formats cents like the API does', () => {
        expect(formatMoney(36300)).toBe('363.00');
        expect(formatMoney(123456789)).toBe('1,234,567.89');
        expect(formatMoney(-250)).toBe('-2.50');
        expect(centsToInput(123456)).toBe('1234.56');
        expect(formatBps(1000)).toBe('10%');
        expect(formatBps(1250)).toBe('12.5%');
    });
});
