<script setup>
import { useId } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    label: { type: String, default: '' },
    /** [{ value, label }] */
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number, null], default: '' });
const id = useId();
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1 block text-sm font-medium text-gray-700">{{ label }}</label>
        <select
            :id="id"
            v-model="model"
            v-bind="$attrs"
            :aria-invalid="!!error"
            class="w-full rounded border bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
            :class="error ? 'border-red-500' : 'border-gray-300'"
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
    </div>
</template>
