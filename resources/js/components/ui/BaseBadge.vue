<script setup>
import { computed } from 'vue';

const props = defineProps({
    color: { type: String, default: 'gray' }, // gray | blue | indigo | purple | green | yellow | orange | red
});

const colors = {
    gray: 'bg-gray-100 text-gray-700',
    blue: 'bg-blue-100 text-blue-800',
    indigo: 'bg-indigo-100 text-indigo-800',
    purple: 'bg-purple-100 text-purple-800',
    green: 'bg-green-100 text-green-800',
    yellow: 'bg-yellow-100 text-yellow-800',
    orange: 'bg-orange-100 text-orange-800',
    red: 'bg-red-100 text-red-800',
};

const classes = computed(() => colors[props.color] ?? colors.gray);
</script>

<template>
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="classes"><slot /></span>
</template>
