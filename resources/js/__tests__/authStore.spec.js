import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import * as authApi from '@/api/auth';

vi.mock('@/api/auth', () => ({ login: vi.fn(), logout: vi.fn(), fetchMe: vi.fn() }));

const staff = { id: 4, name: 'Staff One', roles: ['staff'], permissions: ['notes.create', 'orders.view_assigned'] };

describe('auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('checks permissions with can()', async () => {
        authApi.login.mockResolvedValue(staff);
        const auth = useAuthStore();

        await auth.login({ email: 'staff1@oms.test', password: 'password' });

        expect(auth.isAuthenticated).toBe(true);
        expect(auth.can('notes.create')).toBe(true);
        expect(auth.can('orders.delete')).toBe(false);
    });

    it('treats a failed /me as logged out and marks itself loaded', async () => {
        authApi.fetchMe.mockRejectedValue({ response: { status: 401 } });
        const auth = useAuthStore();

        await auth.fetchUser();

        expect(auth.isAuthenticated).toBe(false);
        expect(auth.loaded).toBe(true);
        expect(auth.can('notes.create')).toBe(false);
    });

    it('clears the user on logout even if the request fails', async () => {
        authApi.login.mockResolvedValue(staff);
        authApi.logout.mockRejectedValue(new Error('Network Error'));
        const auth = useAuthStore();
        await auth.login({});

        await expect(auth.logout()).rejects.toThrow('Network Error');

        expect(auth.isAuthenticated).toBe(false);
    });
});
