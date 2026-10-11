import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import Home from '@/pages/Home.vue';
import BarChart from '@/components/charts/BarChart.vue';
import ColumnChart from '@/components/charts/ColumnChart.vue';
import * as api from '@/api/dashboard';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/dashboard', () => ({
    fetchSummary: vi.fn(),
    fetchRevenueTrend: vi.fn(),
    fetchStatusDistribution: vi.fn(),
    fetchMyWork: vi.fn(),
}));

const summary = {
    period: { from: '2026-09-12', to: '2026-10-11' },
    total_orders: 120,
    orders_by_status: { draft: 3, pending: 12, confirmed: 8, processing: 6, ready: 4, completed: 80, cancelled: 7 },
    booked_orders: 98,
    revenue: 36300000,
    revenue_formatted: '363,000.00',
    average_order_value: 370408,
    average_order_value_formatted: '3,704.08',
    total_customers: 100,
    total_products: 50,
};
const statuses = Object.entries(summary.orders_by_status).map(([value, count]) => ({ value, label: value[0].toUpperCase() + value.slice(1), count }));
const trend = [
    { period_start: '2026-10-09', label: '09 Oct', revenue: 100000, revenue_formatted: '1,000.00', orders: 2 },
    { period_start: '2026-10-10', label: '10 Oct', revenue: 0, revenue_formatted: '0.00', orders: 0 },
    { period_start: '2026-10-11', label: '11 Oct', revenue: 50000, revenue_formatted: '500.00', orders: 1 },
];
const manager = ['dashboard.view', 'orders.view_any', 'orders.view_assigned'];
const routes = namedStubRoutes('orders.index', 'customers.index', 'products.index');

const mountHome = (path = '/', permissions = manager) => mountPage(Home, { path, routePath: '/', routes, permissions });

describe('Dashboard', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        api.fetchSummary.mockResolvedValue(summary);
        api.fetchStatusDistribution.mockResolvedValue(statuses);
        api.fetchRevenueTrend.mockResolvedValue(trend);
    });

    it('shows the ten assessment figures from the API', async () => {
        const { wrapper } = await mountHome();
        await flushPromises();
        const text = wrapper.text();

        for (const [label, value] of [
            ['Total orders', '120'], ['Pending orders', '12'], ['Confirmed orders', '8'], ['Processing orders', '6'],
            ['Completed orders', '80'], ['Cancelled orders', '7'], ['Total revenue', '$363,000.00'],
            ['Average order value', '$3,704.08'], ['Total customers', '100'], ['Total products', '50'],
        ]) {
            expect(text).toContain(`${label}${value}`);
        }
    });

    it('links a status card to the filtered order list', async () => {
        const { wrapper } = await mountHome();
        await flushPromises();

        const pending = wrapper.findAll('a').find((a) => a.text().startsWith('Pending orders'));
        expect(pending.attributes('href')).toContain('status=pending');
        expect(pending.attributes('href')).toContain('date_from=2026-09-12');
    });

    it('sends the URL range and period to the API, and switches the period', async () => {
        const { wrapper, router } = await mountHome('/?from=2026-01-01&to=2026-03-31&period=week');
        await flushPromises();

        expect(api.fetchSummary).toHaveBeenCalledWith({ from: '2026-01-01', to: '2026-03-31' });
        expect(api.fetchRevenueTrend).toHaveBeenCalledWith({ from: '2026-01-01', to: '2026-03-31', period: 'week' });

        await wrapper.findAll('button').find((b) => b.text() === 'Month').trigger('click');
        await flushPromises();

        expect(router.currentRoute.value.query.period).toBe('month');
        expect(api.fetchRevenueTrend).toHaveBeenLastCalledWith({ from: '2026-01-01', to: '2026-03-31', period: 'month' });
    });

    it('draws the charts from the data', async () => {
        const { wrapper } = await mountHome();
        await flushPromises();

        expect(wrapper.findAllComponents(BarChart)[0].findAll('[data-test="bar"]')).toHaveLength(7);
        expect(wrapper.findAllComponents(ColumnChart)[0].findAll('[data-test="column"]')).toHaveLength(3);
        expect(wrapper.find('[aria-label="09 Oct: $1,000.00, 2 orders"]').exists()).toBe(true);
    });

    it('shows an error state with retry', async () => {
        api.fetchSummary.mockRejectedValueOnce(new Error('boom'));
        const { wrapper } = await mountHome();
        await flushPromises();

        expect(wrapper.text()).toContain('Unable to load the dashboard.');
    });

    it('shows staff their own work, not company figures', async () => {
        api.fetchMyWork.mockResolvedValue({ open_orders: 5, orders_by_status: { draft: 0, pending: 2, confirmed: 1, processing: 1, ready: 1, completed: 9, cancelled: 0 } });
        const { wrapper } = await mountHome('/', ['orders.view_assigned']);
        await flushPromises();

        expect(wrapper.text()).toContain('My work');
        expect(wrapper.text()).toContain('Open orders5');
        expect(wrapper.text()).toContain('9 completed so far.');
        expect(wrapper.text()).not.toContain('Total revenue');
        expect(api.fetchSummary).not.toHaveBeenCalled();
    });
});

describe('Charts', () => {
    it('scales bars to the largest value', () => {
        const wrapper = mount(BarChart, { props: { items: [{ key: 'a', label: 'A', value: 50 }, { key: 'b', label: 'B', value: 100 }] } });

        expect(wrapper.findAll('[data-test="bar"]').map((b) => b.element.style.width)).toEqual(['50%', '100%']);
    });

    it('scales columns and thins axis labels', () => {
        const points = Array.from({ length: 30 }, (_, i) => ({ key: String(i), label: `D${i}`, value: i }));
        const wrapper = mount(ColumnChart, { props: { points } });

        expect(wrapper.findAll('[data-test="column"]').at(-1).element.style.height).toBe('100%');
        const shownLabels = wrapper.findAll('.mt-1 span').filter((s) => s.text() !== '');
        expect(shownLabels.length).toBeLessThanOrEqual(8);
    });
});
