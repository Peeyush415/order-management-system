<script setup>
import { useId } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps({
    label: { type: String, default: '' },
    type: { type: String, default: 'text' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const model = defineModel({ type: [String, Number], default: '' });
const id = useId();
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="mb-1 block text-sm font-medium text-gray-700">{{ label }}</label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            v-bind="$attrs"
            :aria-invalid="!!error"
            :aria-describedby="error || hint ? `${id}-help` : undefined"
            class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
            :class="error ? 'border-red-500' : 'border-gray-300'"
        />
        <p v-if="error" :id="`${id}-help`" class="mt-1 text-sm text-red-600">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-help`" class="mt-1 text-xs text-gray-500">{{ hint }}</p>
    </div>
</template>
