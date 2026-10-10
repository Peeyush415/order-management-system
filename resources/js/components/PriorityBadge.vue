<script setup>
import { computed } from 'vue';
import BaseBadge from '@/components/ui/BaseBadge.vue';

const props = defineProps({
    /** { value: 1–4, label } as the API returns it. */
    priority: { type: Object, required: true },
});

const colors = { 1: 'gray', 2: 'blue', 3: 'orange', 4: 'red' };

const color = computed(() => colors[props.priority.value] ?? 'gray');
</script>

<template>
    <BaseBadge :color="color">{{ priority.label }}</BaseBadge>
</template>
