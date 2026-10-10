import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import OrderList from '@/pages/orders/OrderList.vue';
import BaseMultiSelect from '@/components/ui/BaseMultiSelect.vue';
import * as ordersApi from '@/api/orders';
import * as customersApi from '@/api/customers';
import { mountPage } from './helpers';

vi.mock('@/api/orders', () => ({ fetchOrders: vi.fn() }));
vi.mock('@/api/customers', () => ({ fetchCustomers: vi.fn(), fetchCustomer: vi.fn() }));
vi.mock('@/api/lookups', () => ({
    fetchLookups: vi.fn().mockResolvedValue({
        order_statuses: [{ value: 'pending', label: 'Pending' }, { value: 'confirmed', label: 'Confirmed' }],
        order_priorities: [{ value: 3, label: 'High' }, { value: 4, label: 'Urgent' }],
        assignable_users: [{ id: 4, name: 'Staff One' }],
    }),
}));

const order = {
    id: 23, order_number: 'ORD-10023', order_date: '2026-10-01',
    status: { value: 'confirmed', label: 'Confirmed' }, priority: { value: 4, label: 'Urgent' },
    customer: { id: 9, name: 'Kavita Menon', email: 'k@m.test' }, assignee: { id: 4, name: 'Staff One' },
    grand_total: 36300, grand_total_formatted: '363.00',
};
const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const managerPermissions = ['orders.view_any', 'orders.view_assigned', 'orders.create'];

describe('Order list', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        customersApi.fetchCustomers.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });
        customersApi.fetchCustomer.mockResolvedValue({ id: 9, name: 'Kavita Menon' });
    });

    it('sends every URL filter to the API, including multi-value ones', async () => {
        ordersApi.fetchOrders.mockResolvedValue({ data: [order], meta });

        const { wrapper } = await mountPage(OrderList, {
            path: '/orders?status=pending&status=confirmed&priority=4&customer_id=9&date_from=2026-10-01&date_to=2026-10-31&sort=grand_total&direction=asc',
            routePath: '/orders',
            permissions: managerPermissions,
        });
        await flushPromises();

        expect(ordersApi.fetchOrders).toHaveBeenCalledWith({
            status: ['pending', 'confirmed'], priority: ['4'], customer_id: '9',
            date_from: '2026-10-01', date_to: '2026-10-31', sort: 'grand_total', direction: 'asc', page: 1,
        });
        expect(wrapper.text()).toContain('ORD-10023');
        expect(wrapper.text()).toContain('Kavita Menon');
        expect(wrapper.text()).toContain('Urgent');
        expect(wrapper.text()).toContain('$363.00');
        expect(wrapper.text()).toContain('Status: 2 selected');
    });

    it('shows the assignee filter to managers but not to staff', async () => {
        ordersApi.fetchOrders.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });

        const manager = await mountPage(OrderList, { routePath: '/', permissions: managerPermissions });
        const staff = await mountPage(OrderList, { routePath: '/', permissions: ['orders.view_assigned'] });
        await flushPromises();

        expect(manager.wrapper.find('select[aria-label="Assigned to"]').exists()).toBe(true);
        expect(staff.wrapper.find('select[aria-label="Assigned to"]').exists()).toBe(false);
    });

    it('shows the empty and error states from the assessment', async () => {
        ordersApi.fetchOrders.mockResolvedValueOnce({ data: [], meta: { ...meta, total: 0 } });
        const empty = await mountPage(OrderList, { routePath: '/', permissions: managerPermissions });
        await flushPromises();
        expect(empty.wrapper.text()).toContain('No orders found.');
        expect(empty.wrapper.text()).toContain('Try changing your filters or create a new order.');

        ordersApi.fetchOrders.mockRejectedValueOnce(new Error('boom'));
        const failed = await mountPage(OrderList, { routePath: '/', permissions: managerPermissions });
        await flushPromises();
        expect(failed.wrapper.text()).toContain('Unable to load orders.');
    });

    it('a filter change goes to the URL and back to page 1', async () => {
        ordersApi.fetchOrders.mockResolvedValue({ data: [order], meta });

        const { wrapper, router } = await mountPage(OrderList, { path: '/orders?page=3', routePath: '/orders', permissions: managerPermissions });
        await flushPromises();
        await wrapper.find('select[aria-label="Assigned to"]').setValue('4');
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({ assigned_to: '4' });
        expect(ordersApi.fetchOrders).toHaveBeenLastCalledWith(expect.objectContaining({ assigned_to: '4', page: 1 }));
    });
});

describe('BaseMultiSelect', () => {
    afterEach(() => document.body.replaceChildren());

    it('toggles values and summarises the selection', async () => {
        const wrapper = mount(BaseMultiSelect, {
            props: {
                label: 'Status',
                options: [{ value: 'pending', label: 'Pending' }, { value: 'ready', label: 'Ready' }],
                modelValue: [],
                'onUpdate:modelValue': (value) => wrapper.setProps({ modelValue: value }),
            },
            attachTo: document.body,
        });

        expect(wrapper.text()).toContain('Status: All');
        await wrapper.find('button').trigger('click');
        await wrapper.findAll('input[type=checkbox]')[0].setValue(true);
        expect(wrapper.props('modelValue')).toEqual(['pending']);
        expect(wrapper.text()).toContain('Status: Pending');

        await wrapper.findAll('input[type=checkbox]')[1].setValue(true);
        expect(wrapper.text()).toContain('Status: 2 selected');

        // A click outside closes the list.
        document.body.click();
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[role="group"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('treats URL strings and numeric option values as the same', () => {
        const wrapper = mount(BaseMultiSelect, {
            props: { label: 'Priority', options: [{ value: 4, label: 'Urgent' }], modelValue: ['4'] },
        });

        expect(wrapper.text()).toContain('Priority: Urgent');
    });
});
