<script setup>
import { computed, onBeforeUnmount, ref, useId } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    /** [{ value, label }] */
    options: { type: Array, default: () => [] },
});

/** Selected values. Compared as strings, since values read back from the URL are strings. */
const model = defineModel({ type: Array, default: () => [] });

const open = ref(false);
const root = ref(null);
const id = useId();

const selected = computed(() => new Set(model.value.map(String)));
const summary = computed(() => {
    if (selected.value.size === 0) {
        return 'All';
    }
    if (selected.value.size === 1) {
        return props.options.find((o) => String(o.value) === [...selected.value][0])?.label ?? '1 selected';
    }
    return `${selected.value.size} selected`;
});

function toggle(value) {
    const key = String(value);
    model.value = selected.value.has(key) ? model.value.filter((v) => String(v) !== key) : [...model.value, value];
}

// Close on a click outside or on Escape.
function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}
function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}
document.addEventListener('click', onDocumentClick);
document.addEventListener('keydown', onKeydown);
onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded border border-gray-300 bg-white px-3 py-2 text-left text-sm"
            :aria-expanded="open"
            :aria-controls="id"
            @click="open = !open"
        >
            <span><span class="text-gray-500">{{ label }}:</span> {{ summary }}</span>
            <span aria-hidden="true" class="text-gray-400">▾</span>
        </button>
        <div v-if="open" :id="id" role="group" :aria-label="label" class="absolute z-20 mt-1 w-full min-w-44 rounded border border-gray-200 bg-white py-1 shadow-lg">
            <label v-for="option in options" :key="option.value" class="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-gray-50">
                <input type="checkbox" :checked="selected.has(String(option.value))" @change="toggle(option.value)" />
                {{ option.label }}
            </label>
            <button v-if="selected.size" type="button" class="w-full px-3 py-1.5 text-left text-xs text-blue-600 hover:underline" @click="model = []">
                Clear
            </button>
        </div>
    </div>
</template>
