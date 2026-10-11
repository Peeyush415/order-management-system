import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import * as api from '@/api/notifications';

/** How often the bell asks for new notifications (WebSockets would push them instead). */
export const POLL_MS = 30_000;

export function useUnreadCount() {
    return useQuery({
        queryKey: ['notifications', 'unread-count'],
        queryFn: api.fetchUnreadCount,
        refetchInterval: POLL_MS,
        // Keep polling while the tab is in the background, so the count is right when the user comes back.
        refetchIntervalInBackground: true,
    });
}

export function useNotificationList(params, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['notifications', 'list', params],
        queryFn: ({ queryKey }) => api.fetchNotifications(queryKey[2]),
        placeholderData: keepPreviousData,
        enabled,
    });
}

/** Both refresh the count and every list. */
export function useNotificationMutations() {
    const queryClient = useQueryClient();
    const refresh = () => queryClient.invalidateQueries({ queryKey: ['notifications'] });

    return {
        markRead: useMutation({ mutationFn: (id) => api.markNotificationRead(id), onSuccess: refresh }),
        markAllRead: useMutation({ mutationFn: () => api.markAllNotificationsRead(), onSuccess: refresh }),
    };
}

/** Where a notification leads: its order, or the product for a stock alert. */
export function notificationLink(notification, { canManageProducts = false } = {}) {
    if (notification.data?.order_id) {
        return { name: 'orders.show', params: { id: notification.data.order_id } };
    }
    if (notification.data?.product_id && canManageProducts) {
        return { name: 'products.edit', params: { id: notification.data.product_id } };
    }

    return null;
}
