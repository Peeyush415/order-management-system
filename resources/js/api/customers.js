import http from './http';

/** @returns {Promise<{ data: object[], meta: object }>} */
export async function fetchCustomers(params) {
    const { data } = await http.get('/customers', { params });

    return data;
}

/** Includes the order summary (stats). */
export async function fetchCustomer(id) {
    const { data } = await http.get(`/customers/${id}`);

    return data.data;
}

export async function createCustomer(payload) {
    const { data } = await http.post('/customers', payload);

    return data.data;
}

export async function updateCustomer(id, payload) {
    const { data } = await http.put(`/customers/${id}`, payload);

    return data.data;
}

export async function changeCustomerStatus(id, status) {
    const { data } = await http.patch(`/customers/${id}/status`, { status });

    return data.data;
}

export async function deleteCustomer(id) {
    await http.delete(`/customers/${id}`);
}
