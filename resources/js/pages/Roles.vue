<script setup>
import { computed, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { usePermissions, useRoleMutations, useRoles } from '@/composables/useUsers';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const { data: roles, isPending: rolesLoading, isError: rolesFailed, refetch: refetchRoles } = useRoles();
const { data: groups, isPending: groupsLoading, isError: groupsFailed, refetch: refetchGroups } = usePermissions();
const { updatePermissions } = useRoleMutations();

/** Local copy of each role's permissions, edited by the checkboxes: { roleId: Set }. */
const draft = ref({});
function reset() {
    draft.value = Object.fromEntries((roles.value ?? []).map((role) => [role.id, new Set(role.permissions)]));
}
watch(roles, reset, { immediate: true });

const changedRoles = computed(() =>
    (roles.value ?? []).filter((role) => {
        const selected = draft.value[role.id];

        return selected && (selected.size !== role.permissions.length || role.permissions.some((name) => !selected.has(name)));
    }),
);

function toggle(role, permission) {
    const selected = new Set(draft.value[role.id]);
    selected.has(permission) ? selected.delete(permission) : selected.add(permission);
    draft.value = { ...draft.value, [role.id]: selected };
}

const saving = ref(false);

async function save() {
    saving.value = true;
    try {
        // One request per changed role; the API audits each one separately.
        for (const role of changedRoles.value) {
            await updatePermissions.mutateAsync({ id: role.id, permissions: [...draft.value[role.id]] });
        }
        toast.success('Permissions saved. They apply on each user’s next request.');
    } catch (e) {
        toast.error(e.response?.data?.message ?? 'Unable to save permissions.');
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm text-gray-600">
            Roles are bundles of permissions. The app checks permissions, not role names, so changes here need no code change.
            The admin role always keeps every permission.
        </p>

        <div class="rounded-lg border border-gray-200 bg-white">
            <div v-if="rolesLoading || groupsLoading" class="p-4"><SkeletonLoader :lines="8" /></div>
            <ErrorState
                v-else-if="rolesFailed || groupsFailed"
                title="Unable to load roles."
                @retry="
                    refetchRoles();
                    refetchGroups();
                "
            />
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs text-gray-500 uppercase">
                        <tr>
                            <th scope="col" class="px-4 py-2">Permission</th>
                            <th v-for="role in roles" :key="role.id" scope="col" class="px-4 py-2 text-center">
                                {{ role.label }}
                                <span class="block font-normal normal-case">{{ role.users_count }} {{ role.users_count === 1 ? 'user' : 'users' }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody v-for="group in groups" :key="group.group" class="border-t border-gray-100">
                        <tr>
                            <th :colspan="roles.length + 1" scope="rowgroup" class="bg-gray-50/50 px-4 pt-3 pb-1 text-left text-xs font-semibold text-gray-700">
                                {{ group.label }}
                            </th>
                        </tr>
                        <tr v-for="permission in group.permissions" :key="permission.name">
                            <td class="px-4 py-1.5">
                                {{ permission.label }}
                                <code class="ml-1 text-xs text-gray-400">{{ permission.name }}</code>
                            </td>
                            <td v-for="role in roles" :key="role.id" class="px-4 py-1.5 text-center">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-gray-300"
                                    :checked="draft[role.id]?.has(permission.name)"
                                    :disabled="role.locked || saving"
                                    :aria-label="`${role.label}: ${permission.name}`"
                                    @change="toggle(role, permission.name)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2">
            <span v-if="changedRoles.length" class="mr-auto text-sm text-amber-700">
                Unsaved changes: {{ changedRoles.map((role) => role.label).join(', ') }}
            </span>
            <BaseButton variant="secondary" :disabled="!changedRoles.length || saving" @click="reset">Reset</BaseButton>
            <BaseButton :disabled="!changedRoles.length" :loading="saving" @click="save">Save changes</BaseButton>
        </div>
    </div>
</template>
