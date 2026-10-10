<script setup>
defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, required: true },
    /** Highest step reached; earlier steps can be revisited by clicking them. */
    reached: { type: Number, required: true },
});

defineEmits(['go']);
</script>

<template>
    <ol class="flex flex-wrap gap-2" aria-label="Order form steps">
        <li v-for="(label, index) in steps" :key="label">
            <button
                type="button"
                class="flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm"
                :class="[
                    index === current ? 'border-blue-600 bg-blue-600 text-white' : index <= reached ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-400',
                ]"
                :disabled="index > reached"
                :aria-current="index === current ? 'step' : undefined"
                @click="$emit('go', index)"
            >
                <span class="font-semibold">{{ index + 1 }}</span> {{ label }}
            </button>
        </li>
    </ol>
</template>
