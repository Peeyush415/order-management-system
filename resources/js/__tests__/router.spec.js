import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes, setupGuards } from '@/router';
import * as authApi from '@/api/auth';

vi.mock('@/api/auth', () => ({ login: vi.fn(), logout: vi.fn(), fetchMe: vi.fn() }));

// A route that needs a permission, to exercise meta.permission.
const testRoutes = [
    ...routes.slice(0, -1),
    { path: '/admin-only', component: { template: '<div />' }, meta: { requiresAuth: true, permission: 'users.manage' } },
    routes.at(-1),
];

function makeRouter() {
    const router = createRouter({ history: createMemoryHistory(), routes: testRoutes });
    setupGuards(router);

    return router;
}

describe('router guards', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('sends a logged-out user to login and remembers where they were going', async () => {
        authApi.fetchMe.mockRejectedValue({ response: { status: 401 } });
        const router = makeRouter();

        await router.push('/?tab=x');

        expect(router.currentRoute.value.name).toBe('login');
        expect(router.currentRoute.value.query.redirect).toBe('/?tab=x');
    });

    it('sends a logged-in user away from the login page', async () => {
        authApi.fetchMe.mockResolvedValue({ id: 1, roles: ['admin'], permissions: ['users.manage'] });
        const router = makeRouter();

        await router.push('/login');

        expect(router.currentRoute.value.name).toBe('home');
    });

    it('shows forbidden when the permission is missing', async () => {
        authApi.fetchMe.mockResolvedValue({ id: 4, roles: ['staff'], permissions: ['notes.create'] });
        const router = makeRouter();

        await router.push('/admin-only');

        expect(router.currentRoute.value.name).toBe('forbidden');
    });

    it('accepts any one of several permissions', async () => {
        authApi.fetchMe.mockResolvedValue({ id: 4, roles: ['staff'], permissions: ['orders.view_assigned'] });
        const router = makeRouter();

        await router.push('/orders');

        expect(router.currentRoute.value.name).toBe('orders.index');
    });

    it('asks the server only once who is logged in', async () => {
        authApi.fetchMe.mockResolvedValue({ id: 1, roles: ['admin'], permissions: [] });
        const router = makeRouter();

        await router.push('/');
        await router.push('/forbidden');

        expect(authApi.fetchMe).toHaveBeenCalledTimes(1);
    });
});
