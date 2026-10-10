import http from './http';

/** @returns {Promise<{ data: object[], meta: object }>} */
export async function fetchProducts(params) {
    const { data } = await http.get('/products', { params });

    return data;
}

export async function fetchProduct(id) {
    const { data } = await http.get(`/products/${id}`);

    return data.data;
}

/** price is integer cents; initial_stock only on create. */
export async function createProduct(payload) {
    const { data } = await http.post('/products', payload);

    return data.data;
}

export async function updateProduct(id, payload) {
    const { data } = await http.put(`/products/${id}`, payload);

    return data.data;
}

export async function changeProductStatus(id, status) {
    const { data } = await http.patch(`/products/${id}/status`, { status });

    return data.data;
}

/** quantity is signed: +20 received, −3 damaged. */
export async function adjustStock(id, payload) {
    const { data } = await http.post(`/products/${id}/stock-adjustments`, payload);

    return data.data;
}
