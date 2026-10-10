<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import CustomerPicker from '@/components/customers/CustomerPicker.vue';
import PriorityBadge from '@/components/PriorityBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseDatePicker from '@/components/ui/BaseDatePicker.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseMultiSelect from '@/components/ui/BaseMultiSelect.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters';
import { useLookups } from '@/composables/useLookups';
import { useOrders } from '@/composables/useOrders';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const defaults = {
    search: '',
    status: [],
    priority: [],
    customer_id: '',
    assigned_to: '',
    date_from: '',
    date_to: '',
    sort: 'order_date',
    direction: 'desc',
    page: 1,
};
const { filters, update, search } = useListFilters(defaults);

// Empty values are left out, so the API only sees filters that are actually set.
const apiFilters = computed(() =>
    Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v !== '' && !(Array.isArray(v) && !v.length))),
);
const { data, isPending, isError, refetch } = useOrders(apiFilters);
const { data: lookups } = useLookups();

// Staff only ever see their own orders, so an assignee filter means nothing to them.
const canFilterByAssignee = computed(() => auth.can('orders.view_any'));
const canOpen = computed(() => router.hasRoute('orders.show'));
const canCreate = computed(() => router.hasRoute('orders.create'));

const assigneeOptions = computed(() => (lookups.value?.assignable_users ?? []).map((u) => ({ value: String(u.id), label: u.name })));
const sortOptions = [
    { value: 'order_date:desc', label: 'Newest first' },
    { value: 'order_date:asc', label: 'Oldest first' },
    { value: 'grand_total:desc', label: 'Total high–low' },
    { value: 'grand_total:asc', label: 'Total low–high' },
    { value: 'priority:desc', label: 'Priority high–low' },
    { value: 'status:asc', label: 'Status (workflow)' },
    { value: 'customer:asc', label: 'Customer A–Z' },
];

const hasFilters = computed(() => Object.keys(apiFilters.value).some((key) => !['sort', 'direction', 'page'].includes(key)));

const columns = [
    { key: 'order_number', label: 'Order' },
    { key: 'order_date', label: 'Date' },
    { key: 'customer', label: 'Customer' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Priority' },
    { key: 'assignee', label: 'Assigned to', class: 'hidden lg:table-cell' },
    { key: 'grand_total_formatted', label: 'Total', class: 'text-right' },
];

function changeSort(value) {
    const [sort, direction] = value.split(':');
    update({ sort, direction });
}

function clearFilters() {
    search.value = '';
    update({ ...defaults, sort: filters.value.sort, direction: filters.value.direction });
}
</script>

<template>
    <div class="space-y-4">
        <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-end gap-3">
                <BaseInput v-model="search" type="search" placeholder="Order number, customer, product or employee" aria-label="Search orders" class="w-full lg:w-96" />
                <BaseSelect :model-value="`${filters.sort}:${filters.direction}`" :options="sortOptions" aria-label="Sort" class="w-48" @update:model-value="changeSort" />
                <BaseButton v-if="canCreate" v-can="'orders.create'" class="ml-auto" @click="router.push({ name: 'orders.create' })">New order</BaseButton>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                <BaseMultiSelect label="Status" :options="lookups?.order_statuses ?? []" :model-value="filters.status" @update:model-value="update({ status: $event })" />
                <BaseMultiSelect label="Priority" :options="lookups?.order_priorities ?? []" :model-value="filters.priority" @update:model-value="update({ priority: $event })" />
                <CustomerPicker label="" placeholder="Customer…" :model-value="filters.customer_id" @update:model-value="update({ customer_id: $event })" />
                <BaseSelect
                    v-if="canFilterByAssignee"
                    :model-value="filters.assigned_to"
                    :options="assigneeOptions"
                    placeholder="Any assignee"
                    aria-label="Assigned to"
                    @update:model-value="update({ assigned_to: $event })"
                />
                <BaseDatePicker :model-value="filters.date_from" aria-label="From date" @update:model-value="update({ date_from: $event })" />
                <BaseDatePicker :model-value="filters.date_to" aria-label="To date" @update:model-value="update({ date_to: $event })" />
            </div>
            <BaseButton v-if="hasFilters" variant="ghost" size="sm" @click="clearFilters">Clear filters</BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <ErrorState v-if="isError" title="Unable to load orders." message="Try again." @retry="refetch()" />
            <template v-else>
                <DataTable
                    :columns="columns"
                    :rows="data?.data ?? []"
                    :loading="isPending"
                    :clickable="canOpen"
                    empty-title="No orders found."
                    empty-message="Try changing your filters or create a new order."
                    @row-click="router.push({ name: 'orders.show', params: { id: $event.id } })"
                >
                    <template #cell-order_number="{ value }"><span class="font-medium text-gray-900">{{ value }}</span></template>
                    <template #cell-customer="{ row }">{{ row.customer?.name }}</template>
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                    <template #cell-priority="{ row }"><PriorityBadge :priority="row.priority" /></template>
                    <template #cell-assignee="{ row }">{{ row.assignee?.name ?? '—' }}</template>
                    <template #cell-grand_total_formatted="{ value }">${{ value }}</template>
                </DataTable>
                <Pagination v-if="data?.meta?.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
