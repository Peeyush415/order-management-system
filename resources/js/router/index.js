import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

/**
 * meta.requiresAuth: logged-in users only.
 * meta.guestOnly: logged-out users only (login page).
 * meta.permission: also needs this permission, e.g. 'orders.delete'.
 * meta.layout: false renders the page without sidebar and header.
 * meta.title: shown in the header.
 */
export const routes = [
    { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { guestOnly: true, layout: false } },
    { path: '/', name: 'home', component: () => import('@/pages/Home.vue'), meta: { requiresAuth: true, title: 'Dashboard' } },
    { path: '/forbidden', name: 'forbidden', component: () => import('@/pages/Forbidden.vue'), meta: { requiresAuth: true, title: 'Access denied' } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: () => import('@/pages/NotFound.vue'), meta: { layout: false } },
];

export function setupGuards(router) {
    router.beforeEach(async (to) => {
        const auth = useAuthStore();

        // First navigation (or a refresh): ask the server whether the session cookie is still valid.
        if (!auth.loaded) {
            await auth.fetchUser();
        }

        if (to.meta.requiresAuth && !auth.isAuthenticated) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }

        if (to.meta.guestOnly && auth.isAuthenticated) {
            return { name: 'home' };
        }

        if (to.meta.permission && !auth.can(to.meta.permission)) {
            return { name: 'forbidden' };
        }

        return true;
    });
}

const router = createRouter({
    history: createWebHistory(),
    routes,
});

setupGuards(router);

export default router;
