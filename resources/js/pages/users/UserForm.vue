<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useRoles, useUser, useUserMutations } from '@/composables/useUsers';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

/** Same page for create (/users/new) and edit (/users/:id/edit). */
const id = computed(() => route.params.id ?? '');
const isEdit = computed(() => !!id.value);

const form = reactive({ name: '', email: '', role: '', password: '', password_confirmation: '' });

const { data: user, isPending: loading, isError: loadFailed, refetch } = useUser(id);
watch(
    user,
    (value) => {
        if (value) {
            Object.assign(form, { name: value.name, email: value.email, role: value.role ?? '', password: '', password_confirmation: '' });
        }
    },
    { immediate: true },
);

const { data: roles } = useRoles();
const roleOptions = computed(() => (roles.value ?? []).map((role) => ({ value: role.name, label: role.label })));
const isSelf = computed(() => isEdit.value && Number(id.value) === auth.user?.id);

const { create, update, changeStatus } = useUserMutations();
const { error, message, clear, fromResponse } = useFormErrors();
const saving = computed(() => create.isPending.value || update.isPending.value);

async function submit() {
    clear();
    try {
        if (isEdit.value) {
            await update.mutateAsync({ id: id.value, payload: { ...form } });
        } else {
            await create.mutateAsync({ ...form });
        }
        toast.success(isEdit.value ? 'User updated' : 'User created');
        router.push({ name: 'users.index' });
    } catch (e) {
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}

const confirmStatus = ref(false);

async function toggleStatus() {
    try {
        const saved = await changeStatus.mutateAsync({ id: id.value, isActive: !user.value.is_active });
        toast.success(saved.is_active ? 'User activated' : 'User deactivated');
    } catch (e) {
        // Lockout rules (own account, last admin) come back as a message.
        toast.error(e.response?.data?.message ?? 'Unable to change the status.');
    } finally {
        confirmStatus.value = false;
    }
}
</script>

<template>
    <div class="max-w-3xl">
        <SkeletonLoader v-if="isEdit && loading" :lines="6" />
        <ErrorState v-else-if="isEdit && loadFailed" title="Unable to load this user." @retry="refetch()" />

        <div v-else class="space-y-4">
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <BaseCard :title="isEdit ? 'Edit user' : 'New user'">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <BaseInput v-model.trim="form.name" label="Name" :error="error('name')" autocomplete="off" required />
                        <BaseInput v-model.trim="form.email" label="Email" type="email" :error="error('email')" autocomplete="off" required />
                        <BaseSelect
                            v-model="form.role"
                            label="Role"
                            :options="roleOptions"
                            placeholder="Select a role"
                            :error="error('role')"
                            :disabled="isSelf"
                            required
                        />
                    </div>
                    <p v-if="isSelf" class="mt-2 text-xs text-gray-500">You can't change your own role.</p>
                </BaseCard>

                <BaseCard title="Password">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <BaseInput
                            v-model="form.password"
                            :label="isEdit ? 'New password' : 'Password'"
                            type="password"
                            autocomplete="new-password"
                            :hint="isEdit ? 'Leave blank to keep the current password.' : 'At least 8 characters.'"
                            :error="error('password')"
                            :required="!isEdit"
                        />
                        <BaseInput v-model="form.password_confirmation" label="Confirm password" type="password" autocomplete="new-password" />
                    </div>
                </BaseCard>

                <div class="flex justify-end gap-2">
                    <BaseButton variant="secondary" :disabled="saving" @click="router.back()">Cancel</BaseButton>
                    <BaseButton type="submit" :loading="saving">{{ isEdit ? 'Save changes' : 'Create user' }}</BaseButton>
                </div>
            </form>

            <BaseCard v-if="isEdit && user" title="Account status">
                <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <p>
                        <BaseBadge :color="user.is_active ? 'green' : 'gray'">{{ user.is_active ? 'Active' : 'Inactive' }}</BaseBadge>
                        <span class="ml-2 text-gray-500">
                            {{ user.is_active ? 'Can log in.' : 'Cannot log in. Orders and history keep their name.' }}
                        </span>
                    </p>
                    <BaseButton v-if="!isSelf" :variant="user.is_active ? 'danger' : 'secondary'" size="sm" @click="confirmStatus = true">
                        {{ user.is_active ? 'Deactivate' : 'Activate' }}
                    </BaseButton>
                    <span v-else class="text-xs text-gray-500">You can't deactivate your own account.</span>
                </div>
            </BaseCard>
        </div>

        <ConfirmDialog
            :open="confirmStatus"
            :title="user?.is_active ? 'Deactivate user?' : 'Activate user?'"
            :message="user?.is_active ? `${user?.name} will be logged out and can no longer log in.` : `${user?.name} will be able to log in again.`"
            :confirm-label="user?.is_active ? 'Deactivate' : 'Activate'"
            :danger="user?.is_active"
            :loading="changeStatus.isPending.value"
            @confirm="toggleStatus"
            @cancel="confirmStatus = false"
        />
    </div>
</template>
