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
