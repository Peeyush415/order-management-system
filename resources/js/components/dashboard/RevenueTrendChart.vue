<script setup>
import { computed } from 'vue';
import ColumnChart from '@/components/charts/ColumnChart.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useRevenueTrend } from '@/composables/useDashboard';
import { formatMoney } from '@/utils/money';

const props = defineProps({
    range: { type: Object, required: true },
    period: { type: String, default: 'day' },
});

defineEmits(['update:period']);

const params = computed(() => ({ ...props.range, period: props.period }));
const { data, isPending, isError, refetch } = useRevenueTrend(params);

const points = computed(() =>
    (data.value ?? []).map((p) => ({ key: p.period_start, label: p.label, value: p.revenue, detail: `${p.orders} orders` })),
);
const hasRevenue = computed(() => points.value.some((p) => p.value > 0));
const periods = [
    { value: 'day', label: 'Day' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
];
const money = (cents) => `$${formatMoney(cents)}`;
</script>

<template>
    <BaseCard title="Revenue trend">
        <template #actions>
            <div class="flex gap-1" role="group" aria-label="Group revenue by">
                <BaseButton
                    v-for="option in periods"
                    :key="option.value"
                    size="sm"
                    :variant="period === option.value ? 'primary' : 'ghost'"
                    :aria-pressed="period === option.value"
                    @click="$emit('update:period', option.value)"
                >
                    {{ option.label }}
                </BaseButton>
            </div>
        </template>
        <SkeletonLoader v-if="isPending" :lines="6" />
        <ErrorState v-else-if="isError" title="Unable to load the revenue trend." @retry="refetch()" />
        <EmptyState v-else-if="!hasRevenue" title="No revenue in this period." message="Booked orders (confirmed to completed) will show here." />
        <ColumnChart v-else :points="points" :format="money" />
    </BaseCard>
</template>
