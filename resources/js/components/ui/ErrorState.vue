<script setup>
import BaseButton from './BaseButton.vue';

defineProps({
    title: { type: String, default: 'Something went wrong' },
    message: { type: String, default: 'Please try again.' },
});

defineEmits(['retry']);
</script>

<template>
    <div class="px-4 py-10 text-center" role="alert">
        <p class="font-medium text-red-700">{{ title }}</p>
        <p class="mt-1 text-sm text-gray-500">{{ message }}</p>
        <BaseButton class="mt-4" variant="secondary" size="sm" @click="$emit('retry')">Try again</BaseButton>
    </div>
</template>
