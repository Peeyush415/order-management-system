<script setup>
import { useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useListFilters } from '@/composables/useListFilters';
import { useProducts } from '@/composables/useProducts';
import { useAuthStore } from '@/stores/auth';
import { formatBps } from '@/utils/money';

const router = useRouter();
const auth = useAuthStore();

const { filters, update, search } = useListFilters({ search: '', status: '', sort: 'created_at', direction: 'desc', page: 1 });
const { data, isPending, isError, refetch } = useProducts(filters);

const columns = [
    { key: 'sku', label: 'SKU' },
    { key: 'name', label: 'Name' },
    { key: 'price_formatted', label: 'Price', class: 'text-right' },
    { key: 'tax_rate_bps', label: 'Tax', class: 'hidden md:table-cell text-right' },
    { key: 'stock_quantity', label: 'Stock', class: 'text-right' },
    { key: 'status', label: 'Status' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'discontinued', label: 'Discontinued' },
];
const sortOptions = [
    { value: 'created_at:desc', label: 'Newest first' },
    { value: 'name:asc', label: 'Name A–Z' },
    { value: 'price:asc', label: 'Price low–high' },
    { value: 'price:desc', label: 'Price high–low' },
    { value: 'stock_quantity:asc', label: 'Stock low–high' },
];

function changeSort(value) {
    const [sort, direction] = value.split(':');
    update({ sort, direction });
}

// Only product managers have an edit page to open.
function open(product) {
    if (auth.can('products.manage')) {
        router.push({ name: 'products.edit', params: { id: product.id } });
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <BaseInput v-model="search" type="search" placeholder="Search name or SKU" aria-label="Search products" class="w-full sm:w-72" />
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
            <BaseButton v-can="'products.manage'" class="ml-auto" @click="router.push({ name: 'products.create' })">New product</BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <ErrorState v-if="isError" title="Unable to load products." @retry="refetch()" />
            <template v-else>
                <DataTable
                    :columns="columns"
                    :rows="data?.data ?? []"
                    :loading="isPending"
                    :clickable="auth.can('products.manage')"
                    empty-title="No products found."
                    empty-message="Try changing your search or filters."
                    @row-click="open"
                >
                    <template #cell-sku="{ value }"><span class="font-mono text-xs">{{ value }}</span></template>
                    <template #cell-name="{ value }"><span class="font-medium text-gray-900">{{ value }}</span></template>
                    <template #cell-price_formatted="{ value }">${{ value }}</template>
                    <template #cell-tax_rate_bps="{ value }">{{ formatBps(value) }}</template>
                    <template #cell-stock_quantity="{ row }">
                        <span class="inline-flex items-center gap-2">
                            {{ row.stock_quantity }}
                            <BaseBadge v-if="row.is_low_stock" color="orange">Low</BaseBadge>
                        </span>
                    </template>
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                </DataTable>
                <Pagination v-if="data?.meta?.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
