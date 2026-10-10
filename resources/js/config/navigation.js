/**
 * Sidebar links. A link shows only when its route exists (pages appear as they are built)
 * and the user has at least one of its permissions. Hiding links is a convenience:
 * the API enforces every permission itself.
 */
export const navigation = [
    { label: 'Dashboard', route: 'home' },
    { label: 'Orders', route: 'orders.index', permission: ['orders.view_any', 'orders.view_assigned'] },
    { label: 'Customers', route: 'customers.index', permission: 'customers.view' },
    { label: 'Products', route: 'products.index', permission: 'products.view' },
    { label: 'Users', route: 'users.index', permission: 'users.manage' },
    { label: 'Audit log', route: 'audit-logs.index', permission: 'audit.view' },
];

/**
 * A section stays highlighted on its sub-pages too: /customers/90 belongs to "Customers".
 * The dashboard ("/") only matches itself.
 */
export function isActiveLink(linkPath, currentPath) {
    return linkPath === '/' ? currentPath === '/' : currentPath === linkPath || currentPath.startsWith(`${linkPath}/`);
}

/**
 * @param {Array} items
 * @param {{ can: (permission: string) => boolean, hasRoute: (name: string) => boolean }} deps
 */
export function visibleNavigation(items, { can, hasRoute }) {
    return items.filter((item) => hasRoute(item.route) && (!item.permission || [].concat(item.permission).some(can)));
}
