<script setup>
import { computed } from 'vue';
import BaseBadge from '@/components/ui/BaseBadge.vue';

const props = defineProps({
    /** { value, label } as the API returns it. */
    status: { type: Object, required: true },
});

// One colour per status, used for customers, products and orders alike.
const colors = {
    active: 'green',
    inactive: 'gray',
    discontinued: 'red',
    draft: 'gray',
    pending: 'yellow',
    confirmed: 'blue',
    processing: 'indigo',
    ready: 'purple',
    completed: 'green',
    cancelled: 'red',
};

const color = computed(() => colors[props.status.value] ?? 'gray');
</script>

<template>
    <BaseBadge :color="color">{{ status.label }}</BaseBadge>
</template>
