import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import { fetchAuditLogs } from '@/api/auditLogs';

export const AUDIT_EVENTS = [
    'created',
    'updated',
    'deleted',
    'restored',
    'status_changed',
    'assignment_changed',
    'role_changed',
    'password_changed',
    'permissions_changed',
].map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1).replace('_', ' ') }));

export const AUDIT_SUBJECTS = ['order', 'customer', 'product', 'user', 'role'].map((value) => ({ value, label: value[0].toUpperCase() + value.slice(1) }));

/** Empty filters are left out of the request. */
export function useAuditLogs(filters) {
    return useQuery({
        queryKey: ['audit-logs', filters],
        queryFn: ({ queryKey }) => fetchAuditLogs(Object.fromEntries(Object.entries(queryKey[1]).filter(([, value]) => value !== '' && value != null))),
        placeholderData: keepPreviousData,
    });
}

/** Where a row's record can be opened in the app, if anywhere. */
export function subjectLink(subject) {
    const routes = { order: 'orders.show', customer: 'customers.show', product: 'products.edit', user: 'users.edit' };

    return routes[subject.type] ? { name: routes[subject.type], params: { id: subject.id } } : null;
}
