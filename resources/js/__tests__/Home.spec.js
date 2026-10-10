import { describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import Home from '@/pages/Home.vue';
import { fetchHealth } from '@/api/health';

vi.mock('@/api/health', () => ({ fetchHealth: vi.fn() }));

function mountHome() {
    // Fresh client per test, no retries, so error states show immediately.
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    return mount(Home, { global: { plugins: [[VueQueryPlugin, { queryClient }]] } });
}

describe('Home page', () => {
    it('shows the API status when the health check succeeds', async () => {
        fetchHealth.mockResolvedValue({ status: 'ok', version: 'v1', time: '2026-10-10T10:00:00+00:00' });

        const wrapper = mountHome();
        expect(wrapper.text()).toContain('Checking API');

        await flushPromises();

        expect(wrapper.text()).toContain('ok');
        expect(wrapper.text()).toContain('v1');
    });

    it('shows an error with a retry button when the API is down', async () => {
        fetchHealth.mockRejectedValue(new Error('Network Error'));

        const wrapper = mountHome();
        await flushPromises();

        expect(wrapper.text()).toContain('API unreachable: Network Error');
        expect(wrapper.find('button').text()).toBe('Retry');
    });
});
