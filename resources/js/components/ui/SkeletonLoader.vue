<script setup>
defineProps({
    lines: { type: Number, default: 3 },
});
</script>

<template>
    <div class="animate-pulse space-y-2" role="status" aria-label="Loading">
        <div v-for="n in lines" :key="n" class="h-4 rounded bg-gray-200" :class="n === lines && lines > 1 ? 'w-2/3' : 'w-full'" />
    </div>
</template>
