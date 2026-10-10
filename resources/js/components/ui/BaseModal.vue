<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg
});

const emit = defineEmits(['close']);

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl' };

function onKeydown(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

// Listen for Escape only while open.
watch(
    () => props.open,
    (open) => (open ? document.addEventListener('keydown', onKeydown) : document.removeEventListener('keydown', onKeydown)),
    { immediate: true },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" data-test="backdrop" @click="emit('close')" />
            <div
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                class="relative z-10 w-full rounded-lg bg-white shadow-xl"
                :class="widths[size] ?? widths.md"
            >
                <header class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <h2 class="font-semibold text-gray-900">{{ title }}</h2>
                    <button type="button" class="rounded p-1 text-gray-400 hover:text-gray-600" aria-label="Close" @click="emit('close')">✕</button>
                </header>
                <div class="px-5 py-4"><slot /></div>
                <footer v-if="$slots.footer" class="flex justify-end gap-2 border-t border-gray-100 px-5 py-3">
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>
