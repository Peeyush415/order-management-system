<script setup>
import { computed } from 'vue';

/**
 * Vertical columns over time, scaled to the highest value. Hover (or focus) shows the
 * exact value; labels under the axis are thinned out when there are many periods.
 */
const props = defineProps({
    /** [{ key, label, value, detail? }] */
    points: { type: Array, required: true },
    format: { type: Function, default: (value) => String(value) },
    height: { type: Number, default: 200 },
});

const max = computed(() => Math.max(1, ...props.points.map((point) => point.value)));
// About 8 axis labels at most, whatever the number of columns.
const labelEvery = computed(() => Math.max(1, Math.ceil(props.points.length / 8)));
</script>

<template>
    <div>
        <div class="mb-1 text-right text-xs text-gray-400">max {{ format(max) }}</div>
        <div class="flex items-end gap-px border-b border-gray-200" :style="{ height: `${height}px` }" role="list">
            <div
                v-for="point in points"
                :key="point.key"
                role="listitem"
                tabindex="0"
                class="group relative flex h-full flex-1 items-end focus:outline-none"
                :aria-label="`${point.label}: ${format(point.value)}${point.detail ? `, ${point.detail}` : ''}`"
            >
                <div
                    class="w-full rounded-t bg-blue-500 transition group-hover:bg-blue-700 group-focus:bg-blue-700"
                    :style="{ height: `${(point.value / max) * 100}%` }"
                    data-test="column"
                />
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white group-hover:block group-focus:block"
                >
                    {{ point.label }}: {{ format(point.value) }}<template v-if="point.detail"> · {{ point.detail }}</template>
                </div>
            </div>
        </div>
        <div class="mt-1 flex gap-px text-[10px] text-gray-500">
            <span v-for="(point, index) in points" :key="point.key" class="flex-1 truncate text-center">
                {{ index % labelEvery === 0 ? point.label : '' }}
            </span>
        </div>
    </div>
</template>
