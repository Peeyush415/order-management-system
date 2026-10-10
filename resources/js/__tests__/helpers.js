import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createMemoryHistory, createRouter } from 'vue-router';
import { createCan } from '@/directives/can';
import { useAuthStore } from '@/stores/auth';

const Stub = { template: '<div />' };

/**
 * Mounts a page with a memory router, Pinia (logged-in user with the given permissions),
 * a fresh Vue Query client without retries, and the v-can directive.
 * Teleported content (modals) renders in place.
 */
export async function mountPage(component, { path = '/', routePath = '/', permissions = [], routes = [], props = {} } = {}) {
    const pinia = createPinia();
    setActivePinia(pinia);
    useAuthStore().user = { id: 1, name: 'Test User', roles: ['admin'], permissions };

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: routePath, component }, ...routes, { path: '/:pathMatch(.*)*', component: Stub }],
    });
    await router.push(path);
    await router.isReady();

    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    const wrapper = mount(component, {
        props,
        global: {
            plugins: [pinia, router, [VueQueryPlugin, { queryClient }]],
            directives: { can: createCan(pinia) },
            stubs: { teleport: true },
        },
    });

    return { wrapper, router, queryClient };
}

/** Named routes the pages link to, as stubs. */
export const namedStubRoutes = (...names) => names.map((name, i) => ({ path: `/__stub/${i}/:id?`, name, component: Stub }));
