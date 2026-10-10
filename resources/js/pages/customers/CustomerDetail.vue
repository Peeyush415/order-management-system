<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useCustomer, useCustomerMutations } from '@/composables/useCustomers';
import { useOrders } from '@/composables/useOrders';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const id = computed(() => route.params.id);
const { data: customer, isPending, isError, refetch } = useCustomer(id);

// Recent orders come from the order list endpoint, filtered to this customer.
const recentFilters = computed(() => ({ customer_id: id.value, per_page: 5, sort: 'order_date', direction: 'desc' }));
const { data: recent, isPending: recentLoading, isError: recentFailed, refetch: refetchRecent } = useOrders(recentFilters);

const stats = computed(() => {
    const s = customer.value?.stats;
    return s
        ? [
              { label: 'Total orders', value: s.total_orders },
              { label: 'Pending', value: s.pending_orders },
              { label: 'Completed', value: s.completed_orders },
              { label: 'Cancelled', value: s.cancelled_orders },
              { label: 'Total order value', value: `$${s.total_order_value_formatted}` },
          ]
        : [];
});

const orderColumns = [
    { key: 'order_number', label: 'Order' },
    { key: 'order_date', label: 'Date' },
    { key: 'status', label: 'Status' },
    { key: 'grand_total_formatted', label: 'Total', class: 'text-right' },
];
const canOpenOrders = computed(() => router.hasRoute('orders.show'));

const { changeStatus, remove } = useCustomerMutations();
const confirmingDelete = ref(false);

async function toggleStatus() {
    const next = customer.value.status.value === 'active' ? 'inactive' : 'active';
    try {
        await changeStatus.mutateAsync({ id: customer.value.id, status: next });
        toast.success(next === 'active' ? 'Customer activated' : 'Customer deactivated');
    } catch (e) {
        toast.error(e.response?.data?.message ?? 'Could not change the status.');
    }
}

async function destroy() {
    try {
        await remove.mutateAsync(customer.value.id);
        toast.success('Customer deleted');
        router.push({ name: 'customers.index' });
    } catch (e) {
        // e.g. "This customer has orders and cannot be deleted. Deactivate the customer instead."
        toast.error(e.response?.data?.message ?? 'Could not delete the customer.');
    } finally {
        confirmingDelete.value = false;
    }
}
</script>

<template>
    <SkeletonLoader v-if="isPending" :lines="8" />
    <ErrorState v-else-if="isError" title="Unable to load this customer." @retry="refetch()" />

    <div v-else class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ customer.name }}</h2>
                <p class="text-sm text-gray-500">{{ customer.company ?? 'No company' }}</p>
            </div>
            <div class="flex items-center gap-2">
                <StatusBadge :status="customer.status" />
                <BaseButton v-can="'customers.update'" variant="secondary" size="sm" @click="router.push({ name: 'customers.edit', params: { id: customer.id } })">Edit</BaseButton>
                <BaseButton v-can="'customers.update'" variant="secondary" size="sm" :loading="changeStatus.isPending.value" @click="toggleStatus">
                    {{ customer.status.value === 'active' ? 'Deactivate' : 'Activate' }}
                </BaseButton>
                <BaseButton v-can="'customers.delete'" variant="danger" size="sm" @click="confirmingDelete = true">Delete</BaseButton>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div v-for="stat in stats" :key="stat.label" class="rounded-lg border border-gray-200 bg-white p-4">
                <p class="text-xs text-gray-500">{{ stat.label }}</p>
                <p class="mt-1 text-lg font-semibold text-gray-900">{{ stat.value }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <BaseCard title="Contact">
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-500">Email</dt><dd>{{ customer.email }}</dd></div>
                    <div><dt class="text-gray-500">Phone</dt><dd>{{ customer.phone ?? '—' }}</dd></div>
                    <div>
                        <dt class="text-gray-500">Address</dt>
                        <dd>
                            {{ customer.address_line1 }}<template v-if="customer.address_line2">, {{ customer.address_line2 }}</template><br />
                            {{ [customer.city, customer.state, customer.postal_code].filter(Boolean).join(', ') }}<br />
                            {{ customer.country }}
                        </dd>
                    </div>
                </dl>
            </BaseCard>

            <BaseCard title="Recent orders" class="lg:col-span-2">
                <ErrorState v-if="recentFailed" title="Unable to load orders." @retry="refetchRecent()" />
                <DataTable
                    v-else
                    :columns="orderColumns"
                    :rows="recent?.data ?? []"
                    :loading="recentLoading"
                    :clickable="canOpenOrders"
                    empty-title="No orders yet."
                    @row-click="router.push({ name: 'orders.show', params: { id: $event.id } })"
                >
                    <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
                    <template #cell-grand_total_formatted="{ value }">${{ value }}</template>
                </DataTable>
            </BaseCard>
        </div>

        <ConfirmDialog
            :open="confirmingDelete"
            title="Delete customer?"
            :message="`${customer.name} will be removed. Customers with orders cannot be deleted; deactivate them instead.`"
            confirm-label="Delete"
            danger
            :loading="remove.isPending.value"
            @confirm="destroy"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>
