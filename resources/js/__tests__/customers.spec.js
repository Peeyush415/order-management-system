import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import CustomerList from '@/pages/customers/CustomerList.vue';
import CustomerForm from '@/pages/customers/CustomerForm.vue';
import * as api from '@/api/customers';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/customers', () => ({
    fetchCustomers: vi.fn(),
    fetchCustomer: vi.fn(),
    createCustomer: vi.fn(),
    updateCustomer: vi.fn(),
    changeCustomerStatus: vi.fn(),
    deleteCustomer: vi.fn(),
}));

const customer = { id: 7, name: 'Asha Verma', email: 'asha@example.com', company: null, orders_count: 3, status: { value: 'active', label: 'Active' } };
const meta = { current_page: 1, per_page: 15, total: 1, last_page: 1 };
const listRoutes = namedStubRoutes('customers.create', 'customers.show');

describe('Customer list', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows customers and sends the URL filters to the API', async () => {
        api.fetchCustomers.mockResolvedValue({ data: [customer], meta });

        const { wrapper } = await mountPage(CustomerList, { path: '/customers?status=active', routePath: '/customers', routes: listRoutes });
        await flushPromises();

        expect(api.fetchCustomers).toHaveBeenCalledWith(expect.objectContaining({ status: 'active', page: 1 }));
        expect(wrapper.text()).toContain('Asha Verma');
        expect(wrapper.text()).toContain('Active');
        expect(wrapper.text()).toContain('Showing 1–1 of 1');
    });

    it('shows an empty state', async () => {
        api.fetchCustomers.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });

        const { wrapper } = await mountPage(CustomerList, { routePath: '/', routes: listRoutes });
        await flushPromises();

        expect(wrapper.text()).toContain('No customers found.');
    });

    it('shows an error state and retries', async () => {
        api.fetchCustomers.mockRejectedValueOnce(new Error('boom')).mockResolvedValueOnce({ data: [customer], meta });

        const { wrapper } = await mountPage(CustomerList, { routePath: '/', routes: listRoutes });
        await flushPromises();
        expect(wrapper.text()).toContain('Unable to load customers.');

        await wrapper.findAll('button').find((b) => b.text() === 'Try again').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('Asha Verma');
    });

    it('hides "New customer" without the permission', async () => {
        api.fetchCustomers.mockResolvedValue({ data: [], meta: { ...meta, total: 0 } });

        const staff = await mountPage(CustomerList, { routePath: '/', routes: listRoutes, permissions: ['customers.view'] });
        const manager = await mountPage(CustomerList, { routePath: '/', routes: listRoutes, permissions: ['customers.view', 'customers.create'] });
        const newButton = (w) => w.findAll('button').find((b) => b.text() === 'New customer');

        expect(newButton(staff.wrapper).element.style.display).toBe('none');
        expect(newButton(manager.wrapper).element.style.display).toBe('');
    });
});

describe('Customer form', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows server validation errors under the fields', async () => {
        api.createCustomer.mockRejectedValue({
            response: { status: 422, data: { message: 'Invalid', errors: { email: ['A customer with this email already exists.'], city: ['The city field is required.'] } } },
        });

        const { wrapper } = await mountPage(CustomerForm, { routePath: '/', routes: namedStubRoutes('customers.show') });
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('A customer with this email already exists.');
        expect(wrapper.text()).toContain('The city field is required.');
    });

    it('creates the customer and opens it', async () => {
        api.createCustomer.mockResolvedValue({ id: 42 });

        const { wrapper, router } = await mountPage(CustomerForm, { routePath: '/', routes: namedStubRoutes('customers.show') });
        const inputs = wrapper.findAll('input');
        await inputs[0].setValue('Ravi Kumar');
        await inputs[1].setValue('ravi@example.com');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(api.createCustomer).toHaveBeenCalledWith(expect.objectContaining({ name: 'Ravi Kumar', email: 'ravi@example.com' }));
        expect(router.currentRoute.value.params.id).toBe('42');
    });
});
