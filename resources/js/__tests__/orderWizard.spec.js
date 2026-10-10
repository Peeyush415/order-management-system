import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import OrderWizard from '@/pages/orders/OrderWizard.vue';
import * as ordersApi from '@/api/orders';
import * as customersApi from '@/api/customers';
import * as productsApi from '@/api/products';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/orders', () => ({ fetchOrder: vi.fn(), createOrder: vi.fn(), updateOrder: vi.fn() }));
vi.mock('@/api/customers', () => ({ fetchCustomers: vi.fn(), fetchCustomer: vi.fn() }));
vi.mock('@/api/products', () => ({ fetchProducts: vi.fn() }));
vi.mock('@/api/lookups', () => ({
    fetchLookups: vi.fn().mockResolvedValue({
        order_statuses: [],
        order_priorities: [{ value: 1, label: 'Low' }, { value: 2, label: 'Normal' }, { value: 3, label: 'High' }, { value: 4, label: 'Urgent' }],
        assignable_users: [{ id: 4, name: 'Staff One' }],
    }),
}));

const customer = { id: 9, name: 'Kavita Menon', email: 'k@m.test', phone: null, address_line1: '1 Road', city: 'Pune', state: null, postal_code: '411001', country: 'India' };
const productA = { id: 1, name: 'Product A', sku: 'PROD-A', price: 10000, price_formatted: '100.00', tax_rate_bps: 1000, stock_quantity: 50 };
const productB = { id: 2, name: 'Product B', sku: 'PROD-B', price: 5000, price_formatted: '50.00', tax_rate_bps: 1000, stock_quantity: 2 };
const meta = (total) => ({ current_page: 1, per_page: 8, total, last_page: 1 });
const routes = namedStubRoutes('orders.show');

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const button = (wrapper, text) => wrapper.findAll('button').find((b) => b.text() === text);

async function next(wrapper) {
    await button(wrapper, 'Next').trigger('click');
    await flushPromises();
}

async function chooseCustomer(wrapper) {
    await wrapper.find('input[role="combobox"]').trigger('focus');
    await wrapper.find('input[role="combobox"]').setValue('kav');
    await wait(350);
    await flushPromises();
    await wrapper.find('li[role="option"]').trigger('click');
    await flushPromises();
}

async function addProducts(wrapper) {
    await button(wrapper, 'Add').trigger('click'); // Product A
    await flushPromises();
    await button(wrapper, 'Add').trigger('click'); // Product B (A now shows "Added")
    await flushPromises();
    await wrapper.find('#qty-1').setValue('2');
    await wrapper.find('#qty-2').setValue('3');
}

describe('Order wizard — create', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        customersApi.fetchCustomers.mockResolvedValue({ data: [customer], meta: meta(1) });
        customersApi.fetchCustomer.mockResolvedValue(customer);
        productsApi.fetchProducts.mockResolvedValue({ data: [productA, productB], meta: meta(2) });
    });

    const mountCreate = () => mountPage(OrderWizard, { path: '/orders/new', routePath: '/orders/new', routes, permissions: ['orders.create'] });

    it('checks each step before moving on', async () => {
        const { wrapper } = await mountCreate();
        await flushPromises();

        await next(wrapper);
        expect(wrapper.text()).toContain('Customer is required.');
        expect(wrapper.text()).toContain('1. Customer');

        await chooseCustomer(wrapper);
        await next(wrapper);
        expect(wrapper.text()).toContain('2. Products');

        await next(wrapper);
        expect(wrapper.text()).toContain('Order must contain at least one product.');
    });

    it('builds the assessment order, previews $363 and sends no totals', async () => {
        ordersApi.createOrder.mockResolvedValue({ id: 77, order_number: 'ORD-10077' });
        const { wrapper, router } = await mountCreate();
        await flushPromises();

        await chooseCustomer(wrapper);
        await next(wrapper);
        await addProducts(wrapper);
        expect(wrapper.text()).toContain('Only 2 in stock'); // Product B: 3 ordered, 2 in stock — a warning, not a block
        await next(wrapper);

        await wrapper.find('select').setValue('fixed');
        await wrapper.find('input[inputmode="decimal"]').setValue('20');
        expect(wrapper.find('[data-test="grand-total"]').text()).toBe('$363.00');

        await next(wrapper); // assignment
        await next(wrapper); // review
        expect(wrapper.text()).toContain('Kavita Menon');
        await button(wrapper, 'Create order').trigger('click');
        await flushPromises();

        const payload = ordersApi.createOrder.mock.calls[0][0];
        expect(payload).toEqual({
            customer_id: 9,
            items: [{ product_id: 1, quantity: 2 }, { product_id: 2, quantity: 3 }],
            discount_type: 'fixed',
            discount_value: 2000,
            priority: 2,
            order_date: expect.stringMatching(/^\d{4}-\d{2}-\d{2}$/),
            assigned_to: null,
            status: 'pending',
            note: null,
        });
        expect(router.currentRoute.value.params.id).toBe('77');
    });

    it('takes the user to the step with the server error', async () => {
        ordersApi.createOrder.mockRejectedValue({
            response: { status: 422, data: { message: 'Invalid', errors: { 'items.1.quantity': ['Quantity must be greater than 0.'] } } },
        });
        const { wrapper } = await mountCreate();
        await flushPromises();

        await chooseCustomer(wrapper);
        await next(wrapper);
        await addProducts(wrapper);
        for (let i = 0; i < 3; i++) await next(wrapper);
        await button(wrapper, 'Create order').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('2. Products');
        expect(wrapper.text()).toContain('Quantity must be greater than 0.');
    });
});

describe('Order wizard — edit', () => {
    const order = {
        id: 5, order_number: 'ORD-10005', version: 3, order_date: '2026-10-01',
        status: { value: 'pending', label: 'Pending' }, priority: { value: 3, label: 'High' },
        customer: { id: 9, name: 'Kavita Menon' }, discount_type: 'percent', discount_value: 1000,
        items: [{ product_id: 1, product_name: 'Product A', sku: 'PROD-A', unit_price: 10000, tax_rate_bps: 1000, quantity: 1 }],
    };

    beforeEach(() => {
        vi.clearAllMocks();
        customersApi.fetchCustomer.mockResolvedValue(customer);
        customersApi.fetchCustomers.mockResolvedValue({ data: [], meta: meta(0) });
        productsApi.fetchProducts.mockResolvedValue({ data: [], meta: meta(0) });
    });

    const mountEdit = () => mountPage(OrderWizard, { path: '/orders/5/edit', routePath: '/orders/:id/edit', routes, permissions: ['orders.update'] });

    it('sends the loaded version and offers a reload after a conflict', async () => {
        ordersApi.fetchOrder.mockResolvedValue(order);
        ordersApi.updateOrder.mockRejectedValue({ response: { status: 409, data: { message: 'This order was changed by someone else. Reload and try again.' } } });
        const { wrapper } = await mountEdit();
        await flushPromises();

        for (let i = 0; i < 4; i++) await next(wrapper);
        // 10% off 10000 = 1000, tax on 9000 = 900 → 9900
        expect(wrapper.find('[data-test="grand-total"]').text()).toBe('$99.00');
        await button(wrapper, 'Save changes').trigger('click');
        await flushPromises();

        expect(ordersApi.updateOrder).toHaveBeenCalledWith('5', expect.objectContaining({ version: 3, priority: 3, discount_type: 'percent', discount_value: 1000 }));
        expect(ordersApi.updateOrder.mock.calls[0][1]).not.toHaveProperty('assigned_to');
        expect(wrapper.text()).toContain('This order was changed by someone else');

        ordersApi.fetchOrder.mockResolvedValue({ ...order, version: 4 });
        await button(wrapper, 'Reload latest version').trigger('click');
        await flushPromises();
        expect(ordersApi.fetchOrder).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).not.toContain('This order was changed by someone else');
    });

    it('refuses to edit a confirmed order', async () => {
        ordersApi.fetchOrder.mockResolvedValue({ ...order, status: { value: 'confirmed', label: 'Confirmed' } });
        const { wrapper } = await mountEdit();
        await flushPromises();

        expect(wrapper.text()).toContain('This order can no longer be edited');
    });
});
