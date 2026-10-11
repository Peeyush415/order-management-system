<script setup>
import { computed } from 'vue';
import BarChart from '@/components/charts/BarChart.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useStatusDistribution } from '@/composables/useDashboard';

const props = defineProps({
    range: { type: Object, required: true },
});

const { data, isPending, isError, refetch } = useStatusDistribution(computed(() => props.range));

// Same colours as the status badges.
const colors = {
    draft: 'bg-gray-400',
    pending: 'bg-yellow-400',
    confirmed: 'bg-blue-500',
    processing: 'bg-indigo-500',
    ready: 'bg-purple-500',
    completed: 'bg-green-500',
    cancelled: 'bg-red-500',
};

const items = computed(() => (data.value ?? []).map((s) => ({ key: s.value, label: s.label, value: s.count, colorClass: colors[s.value] })));
</script>

<template>
    <BaseCard title="Order status distribution">
        <SkeletonLoader v-if="isPending" :lines="7" />
        <ErrorState v-else-if="isError" title="Unable to load the status distribution." @retry="refetch()" />
        <BarChart v-else :items="items" />
    </BaseCard>
</template>
