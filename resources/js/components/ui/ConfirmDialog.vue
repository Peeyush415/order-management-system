<script setup>
import BaseButton from './BaseButton.vue';
import BaseModal from './BaseModal.vue';

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    danger: { type: Boolean, default: false },
    /** Disables the buttons while the action runs. */
    loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'cancel']);
</script>

<template>
    <BaseModal :open="open" :title="title" size="sm" @close="$emit('cancel')">
        <p class="text-sm text-gray-600">{{ message }}</p>
        <slot />
        <template #footer>
            <BaseButton variant="secondary" :disabled="loading" @click="$emit('cancel')">Cancel</BaseButton>
            <BaseButton :variant="danger ? 'danger' : 'primary'" :loading="loading" data-test="confirm" @click="$emit('confirm')">
                {{ confirmLabel }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
