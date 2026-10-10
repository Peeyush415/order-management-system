import { beforeEach, describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import Home from '@/pages/Home.vue';
import { fetchHealth } from '@/api/health';
import { useAuthStore } from '@/stores/auth';

vi.mock('@/api/health', () => ({ fetchHealth: vi.fn() }));

function mountHome() {
    // Fresh client per test, no retries, so error states show immediately.
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    return mount(Home, { global: { plugins: [[VueQueryPlugin, { queryClient }]] } });
}

describe('Home page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        useAuthStore().user = { id: 1, name: 'Admin User', roles: ['admin'], permissions: [] };
    });

    it('greets the user and shows the API status', async () => {
        fetchHealth.mockResolvedValue({ status: 'ok', version: 'v1', time: '2026-10-10T10:00:00+00:00' });

        const wrapper = mountHome();
        expect(wrapper.find('[role="status"]').exists()).toBe(true); // skeleton while loading

        await flushPromises();

        expect(wrapper.text()).toContain('Welcome back, Admin User');
        expect(wrapper.text()).toContain('ok');
        expect(wrapper.text()).toContain('v1');
    });

    it('shows an error state with a retry button when the API is down', async () => {
        fetchHealth.mockRejectedValue(new Error('Network Error'));

        const wrapper = mountHome();
        await flushPromises();

        expect(wrapper.text()).toContain('Unable to reach the API');
        fetchHealth.mockResolvedValue({ status: 'ok', version: 'v1', time: 'now' });
        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(fetchHealth).toHaveBeenCalledTimes(2);
        expect(wrapper.text()).toContain('ok');
    });
});
