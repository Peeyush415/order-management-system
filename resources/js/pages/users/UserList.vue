<script setup>
import { useRouter } from 'vue-router';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters';
import { useRoles, useUsers } from '@/composables/useUsers';
import { formatDateTime } from '@/utils/date';

const router = useRouter();

const { filters, update, search } = useListFilters({ search: '', role: '', status: '', page: 1 });
const { data, isPending, isError, refetch } = useUsers(filters);
const { data: roles } = useRoles();

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email', class: 'hidden sm:table-cell' },
    { key: 'role', label: 'Role' },
    { key: 'is_active', label: 'Status' },
    { key: 'last_login_at', label: 'Last login', class: 'hidden md:table-cell' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <BaseInput v-model="search" type="search" placeholder="Search name or email" aria-label="Search users" class="w-full sm:w-72" />
            <BaseSelect
                :model-value="filters.role"
                :options="(roles ?? []).map((role) => ({ value: role.name, label: role.label }))"
                placeholder="All roles"
                aria-label="Filter by role"
                class="w-40"
                @update:model-value="update({ role: $event })"
            />
            <BaseSelect
                :model-value="filters.status"
                :options="statusOptions"
                placeholder="All statuses"
                aria-label="Filter by status"
                class="w-40"
                @update:model-value="update({ status: $event })"
            />
            <BaseButton class="ml-auto" @click="router.push({ name: 'users.create' })">New user</BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <ErrorState v-if="isError" title="Unable to load users." @retry="refetch()" />
            <template v-else>
                <DataTable
                    :columns="columns"
                    :rows="data?.data ?? []"
                    :loading="isPending"
                    clickable
                    empty-title="No users found."
                    empty-message="Try changing your search or filters."
                    @row-click="router.push({ name: 'users.edit', params: { id: $event.id } })"
                >
                    <template #cell-name="{ row }"><span class="font-medium text-gray-900">{{ row.name }}</span></template>
                    <template #cell-role="{ value }"><BaseBadge color="indigo">{{ value }}</BaseBadge></template>
                    <template #cell-is_active="{ value }">
                        <BaseBadge :color="value ? 'green' : 'gray'">{{ value ? 'Active' : 'Inactive' }}</BaseBadge>
                    </template>
                    <template #cell-last_login_at="{ value }">{{ value ? formatDateTime(value) : 'Never' }}</template>
                </DataTable>
                <Pagination v-if="data?.meta?.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
