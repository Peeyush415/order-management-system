import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

/**
 * meta.requiresAuth: logged-in users only.
 * meta.guestOnly: logged-out users only (login page).
 * meta.permission: also needs this permission, e.g. 'orders.delete', or any of a list.
 * meta.layout: false renders the page without sidebar and header.
 * meta.title: shown in the header.
 */
export const routes = [
    { path: '/login', name: 'login', component: () => import('@/pages/Login.vue'), meta: { guestOnly: true, layout: false } },
    { path: '/', name: 'home', component: () => import('@/pages/Home.vue'), meta: { requiresAuth: true, title: 'Dashboard' } },
    {
        path: '/orders',
        name: 'orders.index',
        component: () => import('@/pages/orders/OrderList.vue'),
        meta: { requiresAuth: true, permission: ['orders.view_any', 'orders.view_assigned'], title: 'Orders' },
    },
    {
        path: '/orders/new',
        name: 'orders.create',
        component: () => import('@/pages/orders/OrderWizard.vue'),
        meta: { requiresAuth: true, permission: 'orders.create', title: 'New order' },
    },
    {
        path: '/orders/:id/edit',
        name: 'orders.edit',
        component: () => import('@/pages/orders/OrderWizard.vue'),
        meta: { requiresAuth: true, permission: 'orders.update', title: 'Edit order' },
    },
    {
        path: '/orders/:id',
        name: 'orders.show',
        component: () => import('@/pages/orders/OrderDetail.vue'),
        meta: { requiresAuth: true, permission: ['orders.view_any', 'orders.view_assigned'], title: 'Order' },
    },
    {
        path: '/customers',
        name: 'customers.index',
        component: () => import('@/pages/customers/CustomerList.vue'),
        meta: { requiresAuth: true, permission: 'customers.view', title: 'Customers' },
    },
    {
        path: '/customers/new',
        name: 'customers.create',
        component: () => import('@/pages/customers/CustomerForm.vue'),
        meta: { requiresAuth: true, permission: 'customers.create', title: 'New customer' },
    },
    {
        path: '/customers/:id',
        name: 'customers.show',
        component: () => import('@/pages/customers/CustomerDetail.vue'),
        meta: { requiresAuth: true, permission: 'customers.view', title: 'Customer' },
    },
    {
        path: '/customers/:id/edit',
        name: 'customers.edit',
        component: () => import('@/pages/customers/CustomerForm.vue'),
        meta: { requiresAuth: true, permission: 'customers.update', title: 'Edit customer' },
    },
    {
        path: '/products',
        name: 'products.index',
        component: () => import('@/pages/products/ProductList.vue'),
        meta: { requiresAuth: true, permission: 'products.view', title: 'Products' },
    },
    {
        path: '/products/new',
        name: 'products.create',
        component: () => import('@/pages/products/ProductForm.vue'),
        meta: { requiresAuth: true, permission: 'products.manage', title: 'New product' },
    },
    {
        path: '/products/:id/edit',
        name: 'products.edit',
        component: () => import('@/pages/products/ProductForm.vue'),
        meta: { requiresAuth: true, permission: 'products.manage', title: 'Edit product' },
    },
    {
        path: '/notifications',
        name: 'notifications',
        component: () => import('@/pages/Notifications.vue'),
        meta: { requiresAuth: true, title: 'Notifications' },
    },
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

        // One permission, or a list where any one is enough.
        if (to.meta.permission && ![].concat(to.meta.permission).some((permission) => auth.can(permission))) {
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
