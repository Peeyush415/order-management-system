<script setup>
import { computed } from 'vue';

/**
 * Horizontal bars, one per item, scaled to the largest value. Plain HTML/CSS:
 * every bar has a readable label and value, so it works for screen readers too.
 */
const props = defineProps({
    /** [{ key, label, value, colorClass }] — colorClass is a Tailwind bg-* class. */
    items: { type: Array, required: true },
    format: { type: Function, default: (value) => String(value) },
});

const max = computed(() => Math.max(1, ...props.items.map((item) => item.value)));
const width = (value) => `${(value / max.value) * 100}%`;
</script>

<template>
    <ul class="space-y-2">
        <li v-for="item in items" :key="item.key" class="grid grid-cols-[7rem_1fr_3.5rem] items-center gap-3 text-sm" :aria-label="`${item.label}: ${format(item.value)}`">
            <span class="truncate text-gray-600">{{ item.label }}</span>
            <span class="h-3 rounded bg-gray-100">
                <span class="block h-3 rounded" :class="item.colorClass ?? 'bg-blue-500'" :style="{ width: width(item.value) }" data-test="bar" />
            </span>
            <span class="text-right font-medium tabular-nums">{{ format(item.value) }}</span>
        </li>
    </ul>
</template>
