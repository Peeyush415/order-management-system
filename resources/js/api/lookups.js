import http from './http';

/** @returns {Promise<{ order_statuses: object[], order_priorities: object[], assignable_users: object[] }>} */
export async function fetchLookups() {
    const { data } = await http.get('/lookups');

    return data.data;
}
