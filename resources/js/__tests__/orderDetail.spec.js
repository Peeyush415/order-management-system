import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import OrderDetail from '@/pages/orders/OrderDetail.vue';
import * as api from '@/api/orders';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/orders', () => ({
    fetchOrders: vi.fn(),
    fetchOrder: vi.fn(),
    fetchAllowedTransitions: vi.fn(),
    fetchStatusHistory: vi.fn(),
    fetchActivities: vi.fn(),
    fetchNotes: vi.fn(),
    changeOrderStatus: vi.fn(),
    assignOrder: vi.fn(),
    addOrderNote: vi.fn(),
    deleteOrder: vi.fn(),
}));
vi.mock('@/api/lookups', () => ({
    fetchLookups: vi.fn().mockResolvedValue({ order_statuses: [], order_priorities: [], assignable_users: [{ id: 4, name: 'Staff One' }] }),
}));

const order = {
    id: 23, order_number: 'ORD-10023', order_date: '2026-10-07',
    status: { value: 'pending', label: 'Pending' }, priority: { value: 3, label: 'High' },
    customer: { id: 9, name: 'Kavita Menon', email: 'k@m.test', phone: '123', company: null, address: { line1: '1 Road', line2: null, city: 'Pune', state: null, postal_code: '411001', country: 'India' } },
    assignee: null, creator: { id: 1, name: 'Admin User' },
    discount_type: 'fixed', discount_value: 2000,
    subtotal: 35000, discount_total: 2000, tax_total: 3300, grand_total: 36300,
    formatted: { subtotal: '350.00', discount_total: '20.00', tax_total: '33.00', grand_total: '363.00' },
    items: [
        { id: 1, product_id: 1, product_name: 'Product A', sku: 'PROD-A', quantity: 2, unit_price: 10000, tax_rate_bps: 1000, discount_amount: 1143, tax_amount: 1886, line_total: 20743, formatted: { unit_price: '100.00', discount_amount: '11.43', tax_amount: '18.86', line_total: '207.43' } },
    ],
    version: 1, cancellation_reason: null,
};
const page = (data) => ({ data, meta: { current_page: 1, per_page: 20, total: data.length, last_page: 1 } });
const routes = namedStubRoutes('orders.index', 'customers.show', 'orders.edit');
const admin = ['orders.view_any', 'orders.update', 'orders.assign', 'orders.delete', 'orders.change_status', 'orders.cancel', 'notes.create'];

function mountDetail(permissions = admin) {
    return mountPage(OrderDetail, { path: '/orders/23', routePath: '/orders/:id', routes, permissions });
}

const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);

describe('Order detail', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.fetchOrder.mockResolvedValue(order);
        api.fetchAllowedTransitions.mockResolvedValue({
            current: order.status,
            transitions: [
                { value: 'draft', label: 'Draft', requires_remarks: false },
                { value: 'confirmed', label: 'Confirmed', requires_remarks: false },
                { value: 'cancelled', label: 'Cancelled', requires_remarks: true },
            ],
        });
        api.fetchStatusHistory.mockResolvedValue([
            { id: 1, from: null, to: { value: 'pending', label: 'Pending' }, changed_by: { id: 1, name: 'Admin User' }, remarks: null, created_at: '2026-10-07T10:30:00+00:00' },
        ]);
        api.fetchActivities.mockResolvedValue(page([{ id: 5, type: { value: 'created', label: 'Created' }, description: 'Order created as Pending', user: { id: 1, name: 'Admin User' }, created_at: '2026-10-07T10:30:00+00:00' }]));
        api.fetchNotes.mockResolvedValue(page([]));
    });

    it('shows the summary, customer, items with totals, timeline and activity', async () => {
        const { wrapper } = await mountDetail();
        await flushPromises();
        const text = wrapper.text();

        expect(text).toContain('ORD-10023');
        expect(text).toContain('High');
        expect(text).toContain('Unassigned');
        expect(text).toContain('Kavita Menon');
        expect(text).toContain('411001');
        expect(text).toContain('PROD-A');
        expect(text).toContain('−$11.43');
        expect(text).toContain('Grand total$363.00');
        expect(text).toContain('Order created');
        expect(text).toContain('Order created as Pending');
        expect(text).toContain('No notes yet.');
    });

    it('offers only the allowed transitions and needs a reason to cancel', async () => {
        api.changeOrderStatus.mockResolvedValue({ ...order, status: { value: 'cancelled', label: 'Cancelled' } });
        const { wrapper } = await mountDetail();
        await flushPromises();

        await button(wrapper, 'Cancel order').trigger('click');
        const options = wrapper.findAll('#status-form option').map((o) => o.text());
        expect(options).toEqual(['Draft', 'Confirmed', 'Cancelled']);
        expect(wrapper.find('#status-form select').element.value).toBe('cancelled');

        const submit = wrapper.find('button[form="status-form"]');
        expect(submit.attributes('disabled')).toBeDefined();

        await wrapper.find('#status-remarks').setValue('Customer changed their mind');
        expect(wrapper.find('button[form="status-form"]').attributes('disabled')).toBeUndefined();
        await wrapper.find('#status-form').trigger('submit');
        await flushPromises();

        expect(api.changeOrderStatus).toHaveBeenCalledWith('23', { status: 'cancelled', remarks: 'Customer changed their mind' });
    });

    it('shows the stock error from the API in the status modal', async () => {
        api.changeOrderStatus.mockRejectedValue({
            response: { status: 422, data: { message: 'Insufficient stock for Product A: 1 available, 2 requested.', errors: { items: ['Insufficient stock for Product A: 1 available, 2 requested.'] } } },
        });
        const { wrapper } = await mountDetail();
        await flushPromises();

        await button(wrapper, 'Change status').trigger('click');
        await wrapper.find('#status-form select').setValue('confirmed');
        await wrapper.find('#status-form').trigger('submit');
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toBe('Insufficient stock for Product A: 1 available, 2 requested.');
    });

    it('adds a note', async () => {
        api.addOrderNote.mockResolvedValue({ id: 3, body: 'Gift wrap please', user: { id: 1, name: 'Admin User' } });
        const { wrapper } = await mountDetail();
        await flushPromises();

        await wrapper.find('#new-note').setValue('  Gift wrap please  ');
        await wrapper.find('#new-note').element.form.dispatchEvent(new Event('submit'));
        await flushPromises();

        expect(api.addOrderNote).toHaveBeenCalledTimes(1);
        expect(api.addOrderNote).toHaveBeenCalledWith('23', 'Gift wrap please');
        expect(wrapper.find('#new-note').element.value).toBe('');
    });

    it('shows staff only what they may do', async () => {
        api.fetchAllowedTransitions.mockResolvedValue({ current: order.status, transitions: [] });
        const { wrapper } = await mountDetail(['orders.view_assigned', 'orders.change_status', 'notes.create']);
        await flushPromises();
        const labels = wrapper.findAll('button').map((b) => b.text());

        for (const action of ['Change status', 'Assign', 'Edit', 'Cancel order', 'Delete']) {
            expect(labels).not.toContain(action);
        }
        expect(wrapper.find('#new-note').exists()).toBe(true);
    });

    it.each([
        [403, "You don't have access to this order."],
        [404, 'Order not found.'],
    ])('explains a %i', async (status, message) => {
        api.fetchOrder.mockRejectedValue({ response: { status } });
        const { wrapper } = await mountDetail();
        await flushPromises();

        expect(wrapper.text()).toContain(message);
        expect(api.fetchAllowedTransitions).not.toHaveBeenCalled();
    });
});
