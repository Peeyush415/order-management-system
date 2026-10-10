import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import * as authApi from '@/api/auth';

/**
 * Client-side auth state: who is logged in and what they may do.
 * Hiding UI with can() is a convenience only; the API checks every request.
 */
export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    // True once we've asked the server who is logged in (survives a page refresh via the cookie).
    const loaded = ref(false);

    const isAuthenticated = computed(() => user.value !== null);
    const permissions = computed(() => user.value?.permissions ?? []);

    function can(permission) {
        return permissions.value.includes(permission);
    }

    async function fetchUser() {
        try {
            user.value = await authApi.fetchMe();
        } catch {
            user.value = null;
        } finally {
            loaded.value = true;
        }
    }

    async function login(credentials) {
        user.value = await authApi.login(credentials);
        loaded.value = true;
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            reset();
        }
    }

    function reset() {
        user.value = null;
    }

    return { user, loaded, isAuthenticated, permissions, can, fetchUser, login, logout, reset };
});
