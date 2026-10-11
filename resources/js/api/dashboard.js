import http from './http';

/** params: { from?, to? } — the API defaults to the last 30 days. */
export async function fetchSummary(params) {
    const { data } = await http.get('/dashboard/summary', { params });

    return data.data;
}

/** params: { period: 'day'|'week'|'month', from?, to? } */
export async function fetchRevenueTrend(params) {
    const { data } = await http.get('/dashboard/revenue-trend', { params });

    return data.data;
}

export async function fetchStatusDistribution(params) {
    const { data } = await http.get('/dashboard/status-distribution', { params });

    return data.data;
}

/** The logged-in staff member's own orders. */
export async function fetchMyWork() {
    const { data } = await http.get('/dashboard/my-work');

    return data.data;
}
