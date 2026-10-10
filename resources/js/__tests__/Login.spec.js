import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import Login from '@/pages/Login.vue';
import * as authApi from '@/api/auth';

vi.mock('@/api/auth', () => ({ login: vi.fn(), logout: vi.fn(), fetchMe: vi.fn() }));

async function mountLogin(path = '/login') {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/login', component: Login },
            { path: '/', component: { template: '<div>home</div>' } },
            { path: '/orders/5', component: { template: '<div>order</div>' } },
        ],
    });
    await router.push(path);

    const wrapper = mount(Login, { global: { plugins: [router] } });

    return { wrapper, router };
}

async function submit(wrapper, email = 'admin@oms.test', password = 'password') {
    await wrapper.find('#email').setValue(email);
    await wrapper.find('#password').setValue(password);
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

describe('Login page', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('shows the server validation error under the field', async () => {
        authApi.login.mockRejectedValue({
            response: { status: 422, data: { errors: { email: ['These credentials do not match our records.'] } } },
        });
        const { wrapper } = await mountLogin();

        await submit(wrapper, 'admin@oms.test', 'wrong');

        expect(wrapper.text()).toContain('These credentials do not match our records.');
        expect(wrapper.find('button[type=submit]').attributes('disabled')).toBeUndefined();
    });

    it('shows the wait time when throttled', async () => {
        authApi.login.mockRejectedValue({ response: { status: 429, headers: { 'retry-after': '42' }, data: {} } });
        const { wrapper } = await mountLogin();

        await submit(wrapper);

        expect(wrapper.text()).toContain('Please try again in 42 seconds.');
    });

    it('goes back to the page that asked for login', async () => {
        authApi.login.mockResolvedValue({ id: 1, roles: ['admin'], permissions: [] });
        const { wrapper, router } = await mountLogin('/login?redirect=/orders/5');

        await submit(wrapper);

        expect(router.currentRoute.value.fullPath).toBe('/orders/5');
    });

    it('ignores a redirect to another site', async () => {
        authApi.login.mockResolvedValue({ id: 1, roles: ['admin'], permissions: [] });
        const { wrapper, router } = await mountLogin('/login?redirect=//evil.example');

        await submit(wrapper);

        expect(router.currentRoute.value.fullPath).toBe('/');
    });
});
