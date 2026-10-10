import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import * as api from '@/api/orders';

/*
 * Everything the order detail page reads and writes. All keys start with ['order', id],
 * so one invalidation after a change refreshes the order, its timeline, activity,
 * notes and allowed transitions together.
 */

const nextPage = (last) => (last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined);

export function useOrder(id) {
    return useQuery({
        queryKey: ['order', id],
        queryFn: ({ queryKey }) => api.fetchOrder(queryKey[1]),
        enabled: () => !!id.value,
        // A 403/404 won't change by retrying.
        retry: false,
    });
}

export function useOrderTransitions(id, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['order', id, 'transitions'],
        queryFn: ({ queryKey }) => api.fetchAllowedTransitions(queryKey[1]),
        enabled,
    });
}

export function useStatusHistory(id, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['order', id, 'history'],
        queryFn: ({ queryKey }) => api.fetchStatusHistory(queryKey[1]),
        enabled,
    });
}

/** "Load more" pages, newest first. */
export function useOrderActivities(id, { enabled = () => true } = {}) {
    return useInfiniteQuery({
        queryKey: ['order', id, 'activities'],
        queryFn: ({ queryKey, pageParam }) => api.fetchActivities(queryKey[1], pageParam),
        initialPageParam: 1,
        getNextPageParam: nextPage,
        enabled,
    });
}

export function useOrderNotes(id, { enabled = () => true } = {}) {
    return useInfiniteQuery({
        queryKey: ['order', id, 'notes'],
        queryFn: ({ queryKey, pageParam }) => api.fetchNotes(queryKey[1], pageParam),
        initialPageParam: 1,
        getNextPageParam: nextPage,
        enabled,
    });
}

export function useOrderMutations(id) {
    const queryClient = useQueryClient();
    const refresh = () => {
        queryClient.invalidateQueries({ queryKey: ['order', String(id.value)] });
        queryClient.invalidateQueries({ queryKey: ['orders'] });
    };

    return {
        changeStatus: useMutation({ mutationFn: (payload) => api.changeOrderStatus(id.value, payload), onSuccess: refresh }),
        assign: useMutation({ mutationFn: (assignedTo) => api.assignOrder(id.value, assignedTo), onSuccess: refresh }),
        addNote: useMutation({ mutationFn: (body) => api.addOrderNote(id.value, body), onSuccess: refresh }),
        remove: useMutation({
            mutationFn: () => api.deleteOrder(id.value),
            onSuccess: () => queryClient.invalidateQueries({ queryKey: ['orders'] }),
        }),
    };
}

/** Create and update for the order form; both refresh the list and the order. */
export function useOrderFormMutations() {
    const queryClient = useQueryClient();
    const refresh = (order) => {
        queryClient.invalidateQueries({ queryKey: ['orders'] });
        if (order?.id) {
            queryClient.invalidateQueries({ queryKey: ['order', String(order.id)] });
        }
    };

    return {
        create: useMutation({ mutationFn: (payload) => api.createOrder(payload), onSuccess: refresh }),
        update: useMutation({ mutationFn: ({ id, payload }) => api.updateOrder(id, payload), onSuccess: refresh }),
    };
}
