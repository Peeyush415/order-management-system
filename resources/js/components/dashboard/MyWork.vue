<script setup>
import { computed } from 'vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useMyWork } from '@/composables/useDashboard';

/** A staff member's own workload: no revenue or company-wide numbers. */
const { data, isPending, isError, refetch } = useMyWork();

const cards = computed(() =>
    ['pending', 'confirmed', 'processing', 'ready'].map((status) => ({
        status,
        label: status[0].toUpperCase() + status.slice(1),
        value: data.value?.orders_by_status[status] ?? 0,
    })),
);
</script>

<template>
    <BaseCard title="My work">
        <SkeletonLoader v-if="isPending" :lines="3" />
        <ErrorState v-else-if="isError" title="Unable to load your orders." @retry="refetch()" />
        <div v-else class="space-y-4">
            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <StatCard label="Open orders" :value="data.open_orders" :to="{ name: 'orders.index' }" />
                <StatCard v-for="card in cards" :key="card.status" :label="card.label" :value="card.value" :to="{ name: 'orders.index', query: { status: card.status } }" />
            </div>
            <p class="text-sm text-gray-500">{{ data.orders_by_status.completed }} completed so far.</p>
        </div>
    </BaseCard>
</template>
