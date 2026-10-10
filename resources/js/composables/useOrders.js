import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { fetchOrders } from '@/api/orders';

/** Order list for the given filters (a ref/computed). */
export function useOrders(filters, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['orders', filters],
        queryFn: ({ queryKey }) => fetchOrders(queryKey[1]),
        placeholderData: keepPreviousData,
        enabled,
    });
}
