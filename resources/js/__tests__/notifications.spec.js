import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import NotificationBell from '@/components/layout/NotificationBell.vue';
import Notifications from '@/pages/Notifications.vue';
import * as api from '@/api/notifications';
import { notificationLink, POLL_MS } from '@/composables/useNotifications';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/notifications', () => ({
    fetchNotifications: vi.fn(),
    fetchUnreadCount: vi.fn(),
    markNotificationRead: vi.fn(),
    markAllNotificationsRead: vi.fn(),
}));

const unread = {
    id: 'a1',
    kind: 'order_assigned',
    message: 'New order ORD-10001 assigned to you.',
    data: { order_id: 7, order_number: 'ORD-10001' },
    read: false,
    read_at: null,
    created_at: '2026-10-11T10:00:00Z',
};
const read = { ...unread, id: 'a2', message: 'Order ORD-10002 has been confirmed.', data: { order_id: 8 }, read: true };
const page = (items) => ({ data: items, meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length, from: 1, to: items.length } });
const routes = [{ path: '/notifications', name: 'notifications', component: { template: '<div />' } }, ...namedStubRoutes('orders.show', 'products.edit')];

describe('Notifications', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.fetchUnreadCount.mockResolvedValue(1);
        api.fetchNotifications.mockResolvedValue(page([unread, read]));
        api.markNotificationRead.mockResolvedValue({ ...unread, read: true });
        api.markAllNotificationsRead.mockResolvedValue({ marked: 1 });
    });

    it('polls the unread count every 30 seconds', () => {
        expect(POLL_MS).toBe(30_000);
    });

    it('shows the unread badge and loads the list only when opened', async () => {
        const { wrapper } = await mountPage(NotificationBell, { routes });
        await flushPromises();

        expect(wrapper.get('[data-test="unread-badge"]').text()).toBe('1');
        expect(api.fetchNotifications).not.toHaveBeenCalled();

        await wrapper.get('button[aria-label^="Notifications"]').trigger('click');
        await flushPromises();

        expect(api.fetchNotifications).toHaveBeenCalledWith({ per_page: 8 });
        expect(wrapper.text()).toContain('New order ORD-10001 assigned to you.');
    });

    it('marks a notification read and opens its order', async () => {
        const { wrapper, router } = await mountPage(NotificationBell, { routes });
        await flushPromises();
        await wrapper.get('button[aria-label^="Notifications"]').trigger('click');
        await flushPromises();

        const item = wrapper.findAll('li button').find((b) => b.text().includes('ORD-10001'));
        await item.trigger('click');
        await flushPromises();

        expect(api.markNotificationRead).toHaveBeenCalledWith('a1');
        expect(router.currentRoute.value.name).toBe('orders.show');
        expect(router.currentRoute.value.params.id).toBe('7');
    });

    it('hides the badge when nothing is unread', async () => {
        api.fetchUnreadCount.mockResolvedValue(0);
        const { wrapper } = await mountPage(NotificationBell, { routes });
        await flushPromises();

        expect(wrapper.find('[data-test="unread-badge"]').exists()).toBe(false);
    });

    it('page: the Unread tab filters, and mark all as read calls the API', async () => {
        const { wrapper, router } = await mountPage(Notifications, { path: '/notifications', routePath: '/notifications', routes: namedStubRoutes('orders.show') });
        await flushPromises();
        expect(wrapper.text()).toContain('Order ORD-10002 has been confirmed.');

        await wrapper.findAll('[role="tab"]').find((t) => t.text().startsWith('Unread')).trigger('click');
        await flushPromises();
        expect(router.currentRoute.value.query.unread).toBe('1');
        expect(api.fetchNotifications).toHaveBeenLastCalledWith({ page: 1, unread: 1 });

        await wrapper.findAll('button').find((b) => b.text() === 'Mark all as read').trigger('click');
        await flushPromises();
        expect(api.markAllNotificationsRead).toHaveBeenCalled();
    });

    it('page: shows an empty state', async () => {
        api.fetchNotifications.mockResolvedValue(page([]));
        const { wrapper } = await mountPage(Notifications, { path: '/notifications', routePath: '/notifications' });
        await flushPromises();

        expect(wrapper.text()).toContain('No notifications yet.');
    });

    it('links stock alerts to the product only for product managers', () => {
        const alert = { data: { product_id: 3 } };

        expect(notificationLink(alert, { canManageProducts: true })).toEqual({ name: 'products.edit', params: { id: 3 } });
        expect(notificationLink(alert)).toBeNull();
    });
});
