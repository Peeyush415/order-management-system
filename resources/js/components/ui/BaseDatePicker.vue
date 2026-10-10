<script setup>
import BaseInput from './BaseInput.vue';

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
});

/** "YYYY-MM-DD", the format the API expects. */
const model = defineModel({ type: String, default: '' });
</script>

<template>
    <!-- The native date input: accessible, localised and keyboard friendly without a library. -->
    <BaseInput v-model="model" type="date" :label="label" :error="error" />
</template>
