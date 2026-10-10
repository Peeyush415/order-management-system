const dateTime = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
const dateOnly = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

/** ISO timestamp → "10 Oct 2026, 10:30" in the viewer's time zone. */
export function formatDateTime(iso) {
    return iso ? dateTime.format(new Date(iso)) : '—';
}

/** "2026-10-07" → "07 Oct 2026". Read as a calendar date, so no time-zone shift. */
export function formatDate(ymd) {
    if (!ymd) {
        return '—';
    }
    const [year, month, day] = ymd.split('-').map(Number);

    return dateOnly.format(new Date(year, month - 1, day));
}
