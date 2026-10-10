import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ProductForm from '@/pages/products/ProductForm.vue';
import StockAdjustModal from '@/components/products/StockAdjustModal.vue';
import * as api from '@/api/products';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/products', () => ({
    fetchProducts: vi.fn(),
    fetchProduct: vi.fn(),
    createProduct: vi.fn(),
    updateProduct: vi.fn(),
    changeProductStatus: vi.fn(),
    adjustStock: vi.fn(),
}));

const routes = namedStubRoutes('products.index', 'products.edit');

describe('Product form', () => {
    beforeEach(() => vi.clearAllMocks());

    it('sends the price in cents and the tax in basis points', async () => {
        api.createProduct.mockResolvedValue({ id: 9 });

        const { wrapper } = await mountPage(ProductForm, { routePath: '/', routes });
        const input = (label) => wrapper.findAll('label').find((l) => l.text() === label).element.nextElementSibling;
        const set = async (label, value) => {
            const el = input(label);
            el.value = value;
            el.dispatchEvent(new Event('input'));
        };
        await set('SKU', 'kb-100');
        await set('Name', 'Wireless Keyboard');
        await set('Price ($)', '125.50');
        await set('Tax (%)', '18');
        await set('Initial stock', '40');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(api.createProduct).toHaveBeenCalledWith(
            expect.objectContaining({ sku: 'kb-100', price: 12550, tax_rate_bps: 1800, initial_stock: 40 }),
        );
    });

    it('fills the form from the product when editing', async () => {
        api.fetchProduct.mockResolvedValue({
            id: 5, sku: 'KB-100', name: 'Keyboard', description: null, price: 99900, tax_rate_bps: 1000,
            low_stock_threshold: 10, stock_quantity: 3, is_low_stock: true, status: { value: 'active', label: 'Active' },
        });

        const { wrapper } = await mountPage(ProductForm, { path: '/products/5/edit', routePath: '/products/:id/edit', routes });
        await flushPromises();

        expect(wrapper.findAll('input').map((i) => i.element.value)).toEqual(expect.arrayContaining(['KB-100', '999.00', '10']));
        expect(wrapper.text()).toContain('Low stock');
        expect(wrapper.text()).not.toContain('Initial stock');
    });
});

describe('Stock adjustment modal', () => {
    beforeEach(() => vi.clearAllMocks());

    it('sends a signed quantity with the reason', async () => {
        api.adjustStock.mockResolvedValue({ id: 5, stock_quantity: 7 });

        const { wrapper } = await mountPage(StockAdjustModal, { routePath: '/', props: { open: false, product: { id: 5, name: 'Keyboard', stock_quantity: 10 } } });
        await wrapper.setProps({ open: true });
        // Query again after each change: the teleport stub re-renders the modal content.
        await wrapper.findAll('input')[0].setValue('-3');
        await wrapper.findAll('input')[1].setValue('Damaged in storage');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(api.adjustStock).toHaveBeenCalledWith(5, { quantity: -3, reason: 'Damaged in storage' });
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('keeps the modal open and shows the stock error', async () => {
        api.adjustStock.mockRejectedValue({ response: { status: 422, data: { message: 'Insufficient stock for Keyboard: 10 available, 11 requested.', errors: { items: ['…'] } } } });

        const { wrapper } = await mountPage(StockAdjustModal, { routePath: '/', props: { open: false, product: { id: 5, name: 'Keyboard', stock_quantity: 10 } } });
        await wrapper.setProps({ open: true });
        await wrapper.findAll('input')[0].setValue('-11');
        await wrapper.findAll('input')[1].setValue('Count');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.emitted('close')).toBeUndefined();
    });
});
