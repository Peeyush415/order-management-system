/**
 * Money is integer cents everywhere. These helpers convert at the edges only,
 * with string and integer maths (no floating point).
 */

/**
 * "125.50" → 12550, "1,250" → 125000, "7.5" → 750. Returns null for anything else.
 */
export function toCents(input) {
    const value = String(input ?? '')
        .trim()
        .replace(/,/g, '');

    if (!/^\d+(\.\d{0,2})?$/.test(value)) {
        return null;
    }

    const [whole, fraction = ''] = value.split('.');

    return Number(whole) * 100 + Number(`${fraction}00`.slice(0, 2));
}

/** 12550 → "125.50", 123456789 → "1,234,567.89" (matches the API's formatted strings). */
export function formatMoney(cents) {
    const amount = Math.trunc(Number(cents) || 0);
    const absolute = Math.abs(amount);
    const whole = Math.trunc(absolute / 100).toLocaleString('en-US');
    const fraction = String(absolute % 100).padStart(2, '0');

    return `${amount < 0 ? '-' : ''}${whole}.${fraction}`;
}

/** 12550 → "125.50" for an input field (no thousands separator). */
export function centsToInput(cents) {
    return formatMoney(cents).replace(/,/g, '');
}

/** 1000 basis points → "10%", 1250 → "12.5%". */
export function formatBps(bps) {
    return `${Number(bps) / 100}%`;
}
