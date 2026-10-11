<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    /** Optional route location: the card becomes a link (e.g. to the filtered order list). */
    to: { type: Object, default: null },
});
</script>

<template>
    <component
        :is="to ? 'RouterLink' : 'div'"
        :to="to ?? undefined"
        class="block rounded-lg border border-gray-200 bg-white p-4"
        :class="to ? 'transition hover:border-blue-300 hover:shadow-sm' : ''"
    >
        <p class="text-xs font-medium text-gray-500">{{ label }}</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 tabular-nums">{{ value }}</p>
        <p v-if="hint" class="mt-0.5 text-xs text-gray-400">{{ hint }}</p>
    </component>
</template>
