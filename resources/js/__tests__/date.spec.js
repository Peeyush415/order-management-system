import { describe, expect, it } from 'vitest';
import { formatDate, formatDateTime } from '@/utils/date';

describe('date utils', () => {
    it('formats a calendar date without shifting it by the time zone', () => {
        // Parsing "2026-01-01" as UTC midnight would show 31 Dec in time zones west of UTC.
        expect(formatDate('2026-01-01')).toBe('01 Jan 2026');
        expect(formatDate('2026-10-07')).toBe('07 Oct 2026');
    });

    it('shows a dash for missing values', () => {
        expect(formatDate('')).toBe('—');
        expect(formatDate(null)).toBe('—');
        expect(formatDateTime(null)).toBe('—');
    });

    it('formats a timestamp with date and time', () => {
        expect(formatDateTime('2026-10-11T10:30:00Z')).toMatch(/^\d{2} Oct 2026, \d{2}:\d{2}$/);
    });
});
