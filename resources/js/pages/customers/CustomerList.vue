<script setup>
import { useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useCustomers } from '@/composables/useCustomers';
import { useListFilters } from '@/composables/useListFilters';

const router = useRouter();

const { filters, update, search } = useListFilters({ search: '', status: '', sort: 'created_at', direction: 'desc', page: 1 });
const { data, isPending, isError, refetch } = useCustomers(filters);

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'company', label: 'Company', class: 'hidden md:table-cell' },
    { key: 'orders_count', label: 'Orders', class: 'text-right' },
    { key: 'status', label: 'Status' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];
const sortOptions = [
    { value: 'created_at:desc', label: 'Newest first' },
    { value: 'created_at:asc', label: 'Oldest first' },
    { value: 'name:asc', label: 'Name A–Z' },
    { value: 'name:desc', label: 'Name Z–A' },
];

function changeSort(value) {
    const [sort, direction] = value.split(':');
    update({ sort, direction });
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <BaseInput v-model="search" type="search" placeholder="Search name, email or company" aria-label="Search customers" class="w-full sm:w-72" />
            <BaseSelect
                :model-value="filters.status"
                :options="statusOptions"
                placeholder="All statuses"
                aria-label="Filter by status"
                class="w-40"
                @update:model-value="update({ status: $event })"
            />
            <BaseSelect
                :model-value="`${filters.sort}:${filters.direction}`"
                :options="sortOptions"
                aria-label="Sort"
                class="w-44"
                @update:model-value="changeSort"
            />
            <BaseButton v-can="'customers.create'" class="ml-auto" @click="router.push({ name: 'customers.create' })">New customer</BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <ErrorState v-if="isError" title="Unable to load customers." @retry="refetch()" />
            <template v-else>
                <DataTable
                    :columns="columns"
                    :rows="data?.data ?? []"
                    :loading="isPending"
                    clickable
                    empty-title="No customers found."
                    empty-message="Try changing your search or filters."
                    @row-click="router.push({ name: 'customers.show', params: { id: $event.id } })"
                >
                    <template #cell-name="{ row }"><span class="font-medium text-gray-900">{{ row.name }}</span></template>
                    <template #cell-company="{ value }">{{ value ?? '—' }}</template>
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                </DataTable>
                <Pagination v-if="data?.meta?.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
