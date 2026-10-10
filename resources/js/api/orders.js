import http from './http';

/**
 * Arrays go out as status[]=a&status[]=b, the format Laravel reads.
 *
 * @returns {Promise<{ data: object[], meta: object }>}
 */
export async function fetchOrders(params) {
    const { data } = await http.get('/orders', { params });

    return data;
}

/** Full order: customer, assignee, creator, items, totals, version. */
export async function fetchOrder(id) {
    const { data } = await http.get(`/orders/${id}`);

    return data.data;
}

/** @returns {Promise<{ current: object, transitions: { value, label, requires_remarks }[] }>} */
export async function fetchAllowedTransitions(id) {
    const { data } = await http.get(`/orders/${id}/allowed-transitions`);

    return data.data;
}

/** Oldest first. */
export async function fetchStatusHistory(id) {
    const { data } = await http.get(`/orders/${id}/status-history`);

    return data.data;
}

/** Newest first, paginated: { data, meta }. */
export async function fetchActivities(id, page = 1) {
    const { data } = await http.get(`/orders/${id}/activities`, { params: { page } });

    return data;
}

/** Newest first, paginated: { data, meta }. */
export async function fetchNotes(id, page = 1) {
    const { data } = await http.get(`/orders/${id}/notes`, { params: { page } });

    return data;
}

/** remarks is required when cancelling. */
export async function changeOrderStatus(id, payload) {
    const { data } = await http.post(`/orders/${id}/status`, payload);

    return data.data;
}

/** assignedTo null unassigns. */
export async function assignOrder(id, assignedTo) {
    const { data } = await http.post(`/orders/${id}/assign`, { assigned_to: assignedTo });

    return data.data;
}

export async function addOrderNote(id, body) {
    const { data } = await http.post(`/orders/${id}/notes`, { body });

    return data.data;
}

export async function deleteOrder(id) {
    await http.delete(`/orders/${id}`);
}
