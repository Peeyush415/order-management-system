import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import * as api from '@/api/customers';

/** Customer list for the given filters (a ref/computed). Keeps the old page visible while the next loads. */
export function useCustomers(filters) {
    return useQuery({
        queryKey: ['customers', filters],
        queryFn: ({ queryKey }) => api.fetchCustomers(queryKey[1]),
        placeholderData: keepPreviousData,
    });
}

/** One customer with its order summary. id is a ref; disabled while empty (new customer form). */
export function useCustomer(id) {
    return useQuery({
        queryKey: ['customer', id],
        queryFn: ({ queryKey }) => api.fetchCustomer(queryKey[1]),
        enabled: () => !!id.value,
    });
}

/** Writes; each one refreshes the list and the changed customer. */
export function useCustomerMutations() {
    const queryClient = useQueryClient();
    const refresh = (customer) => {
        queryClient.invalidateQueries({ queryKey: ['customers'] });
        if (customer?.id) {
            queryClient.invalidateQueries({ queryKey: ['customer', String(customer.id)] });
        }
    };

    return {
        // Wrapped so only the payload reaches the API (Vue Query passes a second context argument).
        create: useMutation({ mutationFn: (payload) => api.createCustomer(payload), onSuccess: refresh }),
        update: useMutation({ mutationFn: ({ id, payload }) => api.updateCustomer(id, payload), onSuccess: refresh }),
        changeStatus: useMutation({ mutationFn: ({ id, status }) => api.changeCustomerStatus(id, status), onSuccess: refresh }),
        remove: useMutation({ mutationFn: (id) => api.deleteCustomer(id), onSuccess: () => refresh() }),
    };
}
