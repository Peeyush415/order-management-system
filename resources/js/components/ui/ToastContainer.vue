<script setup>
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const styles = {
    success: 'border-green-200 bg-green-50 text-green-800',
    error: 'border-red-200 bg-red-50 text-red-800',
    info: 'border-blue-200 bg-blue-50 text-blue-800',
};
</script>

<template>
    <!-- aria-live: screen readers announce new messages. -->
    <div class="fixed right-4 bottom-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" aria-live="polite">
        <div
            v-for="item in toast.toasts"
            :key="item.id"
            class="flex items-start justify-between gap-3 rounded-lg border px-4 py-3 text-sm shadow"
            :class="styles[item.type] ?? styles.info"
            role="status"
        >
            <span>{{ item.message }}</span>
            <button type="button" class="opacity-60 hover:opacity-100" aria-label="Dismiss" @click="toast.dismiss(item.id)">✕</button>
        </div>
    </div>
</template>
