import { describe, expect, it } from 'vitest';
import { navigation, visibleNavigation } from '@/config/navigation';
import { routes } from '@/router';

// The role bundles from database/seeders/RolePermissionSeeder.php.
const STAFF = ['orders.view_assigned', 'orders.change_status', 'notes.create', 'customers.view', 'products.view'];
const MANAGER = [
    'dashboard.view',
    'reports.view',
    'orders.view_any',
    'orders.view_assigned',
    'orders.create',
    'orders.update',
    'orders.assign',
    'orders.change_status',
    'orders.cancel',
    'notes.create',
    'customers.view',
    'customers.create',
    'customers.update',
    'products.view',
];

const routeByName = Object.fromEntries(routes.filter((route) => route.name).map((route) => [route.name, route]));
const hasRoute = (name) => name in routeByName;
const labelsFor = (permissions) => visibleNavigation(navigation, { can: (p) => permissions.includes(p), hasRoute }).map((link) => link.label);

describe('navigation and routes agree', () => {
    it('every sidebar link points to a real route with the same permission', () => {
        for (const link of navigation) {
            expect(routeByName[link.route], link.route).toBeDefined();
            expect(routeByName[link.route].meta.permission).toEqual(link.permission);
        }
    });

    it('every page except login and not-found needs a login', () => {
        const open = routes.filter((route) => !route.meta?.requiresAuth).map((route) => route.name);

        expect(open).toEqual(['login', 'not-found']);
    });

    it('admin-only pages need admin permissions', () => {
        expect(routeByName['users.index'].meta.permission).toBe('users.manage');
        expect(routeByName['roles.index'].meta.permission).toBe('roles.manage');
        expect(routeByName['audit-logs.index'].meta.permission).toBe('audit.view');
        expect(routeByName['orders.create'].meta.permission).toBe('orders.create');
        expect(routeByName['products.create'].meta.permission).toBe('products.manage');
    });

    it.each([
        ['staff', STAFF, ['Dashboard', 'Orders', 'Customers', 'Products']],
        ['manager', MANAGER, ['Dashboard', 'Orders', 'Customers', 'Products']],
    ])('%s sees only their links', (_, permissions, expected) => {
        expect(labelsFor(permissions)).toEqual(expected);
    });

    it('admin sees every link', () => {
        expect(visibleNavigation(navigation, { can: () => true, hasRoute })).toHaveLength(navigation.length);
    });
});
