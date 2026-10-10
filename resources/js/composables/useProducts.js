import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import * as api from '@/api/products';

/** Product list for the given filters (a ref/computed). */
export function useProducts(filters) {
    return useQuery({
        queryKey: ['products', filters],
        queryFn: ({ queryKey }) => api.fetchProducts(queryKey[1]),
        placeholderData: keepPreviousData,
    });
}

/** One product; disabled while id is empty (new product form). */
export function useProduct(id) {
    return useQuery({
        queryKey: ['product', id],
        queryFn: ({ queryKey }) => api.fetchProduct(queryKey[1]),
        enabled: () => !!id.value,
    });
}

/** Writes; each one refreshes the list and the changed product. */
export function useProductMutations() {
    const queryClient = useQueryClient();
    const refresh = (product) => {
        queryClient.invalidateQueries({ queryKey: ['products'] });
        if (product?.id) {
            queryClient.invalidateQueries({ queryKey: ['product', String(product.id)] });
        }
    };

    return {
        // Wrapped so only the payload reaches the API (Vue Query passes a second context argument).
        create: useMutation({ mutationFn: (payload) => api.createProduct(payload), onSuccess: refresh }),
        update: useMutation({ mutationFn: ({ id, payload }) => api.updateProduct(id, payload), onSuccess: refresh }),
        changeStatus: useMutation({ mutationFn: ({ id, status }) => api.changeProductStatus(id, status), onSuccess: refresh }),
        adjustStock: useMutation({ mutationFn: ({ id, payload }) => api.adjustStock(id, payload), onSuccess: refresh }),
    };
}
