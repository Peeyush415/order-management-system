import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import UserList from '@/pages/users/UserList.vue';
import UserForm from '@/pages/users/UserForm.vue';
import Roles from '@/pages/Roles.vue';
import AuditLogs from '@/pages/AuditLogs.vue';
import * as usersApi from '@/api/users';
import * as rolesApi from '@/api/roles';
import * as auditApi from '@/api/auditLogs';
import { mountPage, namedStubRoutes } from './helpers';

vi.mock('@/api/users', () => ({
    fetchUsers: vi.fn(),
    fetchUser: vi.fn(),
    createUser: vi.fn(),
    updateUser: vi.fn(),
    changeUserStatus: vi.fn(),
}));
vi.mock('@/api/roles', () => ({ fetchRoles: vi.fn(), fetchPermissions: vi.fn(), updateRolePermissions: vi.fn() }));
vi.mock('@/api/auditLogs', () => ({ fetchAuditLogs: vi.fn() }));

const page = (items) => ({ data: items, meta: { current_page: 1, last_page: 1, per_page: 15, total: items.length } });
const roles = [
    { id: 1, name: 'admin', label: 'Admin', locked: true, users_count: 1, permissions: ['orders.delete', 'users.manage'] },
    { id: 3, name: 'staff', label: 'Staff', locked: false, users_count: 3, permissions: ['notes.create'] },
];
const groups = [
    { group: 'orders', label: 'Orders', permissions: [{ name: 'orders.delete', label: 'Delete' }] },
    { group: 'notes', label: 'Notes', permissions: [{ name: 'notes.create', label: 'Create' }] },
];
const staffUser = { id: 7, name: 'Sita Staff', email: 'sita@oms.test', role: 'staff', is_active: true, last_login_at: null };
const admin = ['users.manage', 'roles.manage', 'audit.view'];
const validation = (errors) => Object.assign(new Error('422'), { response: { status: 422, data: { message: 'Invalid', errors } } });

describe('Users, roles and audit log', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        usersApi.fetchUsers.mockResolvedValue(page([staffUser]));
        usersApi.fetchUser.mockResolvedValue(staffUser);
        rolesApi.fetchRoles.mockResolvedValue(roles);
        rolesApi.fetchPermissions.mockResolvedValue(groups);
    });

    it('lists users and filters by role through the URL', async () => {
        const { wrapper, router } = await mountPage(UserList, { path: '/users', routePath: '/users', permissions: admin, routes: namedStubRoutes('users.create', 'users.edit') });
        await flushPromises();

        expect(wrapper.text()).toContain('Sita Staff');
        expect(wrapper.text()).toContain('Never');

        await wrapper.get('select[aria-label="Filter by role"]').setValue('staff');
        await flushPromises();
        expect(router.currentRoute.value.query.role).toBe('staff');
        expect(usersApi.fetchUsers).toHaveBeenLastCalledWith(expect.objectContaining({ role: 'staff' }));
    });

    it('creates a user and shows server validation errors on the fields', async () => {
        usersApi.createUser.mockRejectedValueOnce(validation({ email: ['A user with this email already exists.'] }));
        const { wrapper } = await mountPage(UserForm, { path: '/users/new', routePath: '/users/new', permissions: admin, routes: namedStubRoutes('users.index') });
        await flushPromises();

        const inputs = wrapper.findAll('input');
        await inputs[0].setValue('Ravi');
        await inputs[1].setValue('ravi@oms.test');
        await wrapper.get('select').setValue('staff');
        await inputs[2].setValue('secret-pass');
        await inputs[3].setValue('secret-pass');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(usersApi.createUser).toHaveBeenCalledWith({
            name: 'Ravi',
            email: 'ravi@oms.test',
            role: 'staff',
            password: 'secret-pass',
            password_confirmation: 'secret-pass',
        });
        expect(wrapper.text()).toContain('A user with this email already exists.');
    });

    it('does not offer deactivate or a role change on your own account', async () => {
        usersApi.fetchUser.mockResolvedValue({ ...staffUser, id: 1, role: 'admin' });
        const { wrapper } = await mountPage(UserForm, { path: '/users/1/edit', routePath: '/users/:id/edit', permissions: admin });
        await flushPromises();

        expect(wrapper.get('select').attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain("You can't deactivate your own account.");
        expect(wrapper.findAll('button').some((b) => b.text() === 'Deactivate')).toBe(false);
    });

    it('deactivates another user after confirmation', async () => {
        usersApi.changeUserStatus.mockResolvedValue({ ...staffUser, is_active: false });
        const { wrapper } = await mountPage(UserForm, { path: '/users/7/edit', routePath: '/users/:id/edit', permissions: admin });
        await flushPromises();

        await wrapper.findAll('button').find((b) => b.text() === 'Deactivate').trigger('click');
        await wrapper.get('[data-test="confirm"]').trigger('click');
        await flushPromises();

        expect(usersApi.changeUserStatus).toHaveBeenCalledWith('7', false);
    });

    it('roles matrix: admin is locked, and only changed roles are saved', async () => {
        rolesApi.updateRolePermissions.mockResolvedValue(roles[1]);
        const { wrapper } = await mountPage(Roles, { path: '/roles', routePath: '/roles', permissions: admin });
        await flushPromises();

        const box = (label) => wrapper.get(`input[aria-label="${label}"]`);
        expect(box('Admin: orders.delete').attributes('disabled')).toBeDefined();
        expect(box('Staff: notes.create').element.checked).toBe(true);

        await box('Staff: orders.delete').setValue(true);
        expect(wrapper.text()).toContain('Unsaved changes: Staff');

        await wrapper.findAll('button').find((b) => b.text() === 'Save changes').trigger('click');
        await flushPromises();

        expect(rolesApi.updateRolePermissions).toHaveBeenCalledTimes(1);
        expect(rolesApi.updateRolePermissions).toHaveBeenCalledWith(3, ['notes.create', 'orders.delete']);
    });

    it('audit log shows who, what and old → new values, and filters by action', async () => {
        auditApi.fetchAuditLogs.mockResolvedValue(
            page([
                {
                    id: 1,
                    event: 'assignment_changed',
                    event_label: 'Assignment changed',
                    user: { id: 1, name: 'Admin One' },
                    subject: { type: 'order', type_label: 'Order', id: 5, label: 'ORD-10234' },
                    changes: [
                        { field: 'priority', label: 'Priority', old: 'Normal', new: 'High' },
                        { field: 'assigned_to', label: 'Assigned to', old: 'John', new: 'David' },
                    ],
                    created_at: '2026-10-11T10:00:00Z',
                },
            ]),
        );
        const { wrapper, router } = await mountPage(AuditLogs, { path: '/audit-logs', routePath: '/audit-logs', permissions: admin, routes: namedStubRoutes('orders.show') });
        await flushPromises();

        const row = wrapper.get('[data-test="audit-row"]').text();
        expect(row).toContain('Admin One');
        expect(row).toContain('ORD-10234');
        expect(row).toContain('Priority: Normal → High');
        expect(row).toContain('Assigned to: John → David');
        // Empty filters are not sent.
        expect(auditApi.fetchAuditLogs).toHaveBeenCalledWith({ page: 1 });

        const action = wrapper.findAll('select').find((s) => s.text().includes('All actions'));
        await action.setValue('status_changed');
        await flushPromises();
        expect(router.currentRoute.value.query.event).toBe('status_changed');
        expect(auditApi.fetchAuditLogs).toHaveBeenLastCalledWith({ event: 'status_changed', page: 1 });
    });
});
