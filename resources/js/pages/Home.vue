<script setup>
import { computed } from 'vue';
import DateRangeFilter from '@/components/dashboard/DateRangeFilter.vue';
import MyWork from '@/components/dashboard/MyWork.vue';
import RevenueTrendChart from '@/components/dashboard/RevenueTrendChart.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import StatusDistributionChart from '@/components/dashboard/StatusDistributionChart.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useDashboardSummary } from '@/composables/useDashboard';
import { useListFilters } from '@/composables/useListFilters';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const canSeeDashboard = computed(() => auth.can('dashboard.view'));
const canSeeOwnWork = computed(() => !canSeeDashboard.value && auth.can('orders.view_assigned'));

// Range and period live in the URL like any other filter; empty means the API default (last 30 days).
const { filters, update } = useListFilters({ from: '', to: '', period: 'day' });
const range = computed(() => Object.fromEntries(Object.entries({ from: filters.value.from, to: filters.value.to }).filter(([, v]) => v)));

const { data: summary, isPending, isError, refetch } = useDashboardSummary(range, { enabled: () => canSeeDashboard.value });

// The range the API actually used, for the date inputs and the card links.
const period = computed(() => summary.value?.period ?? { from: '', to: '' });
const ordersLink = (extra = {}) => ({ name: 'orders.index', query: { date_from: period.value.from, date_to: period.value.to, ...extra } });

const cards = computed(() => {
    const s = summary.value;
    if (!s) {
        return [];
    }
    const status = (key, label) => ({ label, value: s.orders_by_status[key], to: ordersLink({ status: key }) });

    return [
        { label: 'Total orders', value: s.total_orders, to: ordersLink() },
        status('pending', 'Pending orders'),
        status('confirmed', 'Confirmed orders'),
        status('processing', 'Processing orders'),
        status('completed', 'Completed orders'),
        status('cancelled', 'Cancelled orders'),
        { label: 'Total revenue', value: `$${s.revenue_formatted}`, hint: `${s.booked_orders} booked orders` },
        { label: 'Average order value', value: `$${s.average_order_value_formatted}` },
        { label: 'Total customers', value: s.total_customers, to: { name: 'customers.index' } },
        { label: 'Total products', value: s.total_products, to: { name: 'products.index' } },
    ];
});
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-lg font-medium text-gray-900">Welcome back, {{ auth.user?.name }}</p>
                <p v-if="canSeeDashboard" class="text-sm text-gray-500">Revenue counts booked orders: confirmed, processing, ready and completed.</p>
            </div>
            <DateRangeFilter v-if="canSeeDashboard && summary" :from="period.from" :to="period.to" @change="update($event)" />
        </div>

        <template v-if="canSeeDashboard">
            <div v-if="isPending" class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div v-for="n in 10" :key="n" class="rounded-lg border border-gray-200 bg-white p-4"><SkeletonLoader :lines="2" /></div>
            </div>
            <div v-else-if="isError" class="rounded-lg border border-gray-200 bg-white">
                <ErrorState title="Unable to load the dashboard." @retry="refetch()" />
            </div>
            <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <StatCard v-for="card in cards" :key="card.label" v-bind="card" />
            </div>

            <div class="grid gap-6 lg:grid-cols-5">
                <RevenueTrendChart
                    class="lg:col-span-3"
                    :range="range"
                    :period="filters.period"
                    @update:period="update({ period: $event })"
                />
                <StatusDistributionChart class="lg:col-span-2" :range="range" />
            </div>
        </template>

        <MyWork v-else-if="canSeeOwnWork" />

        <BaseCard v-else>
            <p class="text-sm text-gray-500">Use the menu to get started.</p>
        </BaseCard>
    </div>
</template>
