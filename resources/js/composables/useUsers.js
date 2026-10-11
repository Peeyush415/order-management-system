import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/vue-query';
import * as api from '@/api/users';
import * as rolesApi from '@/api/roles';

/** User list for the given filters (a ref/computed). */
export function useUsers(filters, { enabled = true } = {}) {
    return useQuery({
        queryKey: ['users', filters],
        queryFn: ({ queryKey }) => api.fetchUsers(queryKey[1]),
        placeholderData: keepPreviousData,
        enabled,
    });
}

/** One user. id is a ref; disabled while empty (new user form). */
export function useUser(id) {
    return useQuery({
        queryKey: ['user', id],
        queryFn: ({ queryKey }) => api.fetchUser(queryKey[1]),
        enabled: () => !!id.value,
    });
}

export function useUserMutations() {
    const queryClient = useQueryClient();
    const refresh = (user) => {
        queryClient.invalidateQueries({ queryKey: ['users'] });
        // Role user counts change too.
        queryClient.invalidateQueries({ queryKey: ['roles'] });
        if (user?.id) {
            queryClient.invalidateQueries({ queryKey: ['user', String(user.id)] });
        }
    };

    return {
        create: useMutation({ mutationFn: (payload) => api.createUser(payload), onSuccess: refresh }),
        update: useMutation({ mutationFn: ({ id, payload }) => api.updateUser(id, payload), onSuccess: refresh }),
        changeStatus: useMutation({ mutationFn: ({ id, isActive }) => api.changeUserStatus(id, isActive), onSuccess: refresh }),
    };
}

export function useRoles() {
    return useQuery({ queryKey: ['roles'], queryFn: rolesApi.fetchRoles });
}

export function usePermissions() {
    return useQuery({ queryKey: ['permissions'], queryFn: rolesApi.fetchPermissions, staleTime: Infinity });
}

export function useRoleMutations() {
    const queryClient = useQueryClient();

    return {
        updatePermissions: useMutation({
            mutationFn: ({ id, permissions }) => rolesApi.updateRolePermissions(id, permissions),
            onSuccess: () => queryClient.invalidateQueries({ queryKey: ['roles'] }),
        }),
    };
}
