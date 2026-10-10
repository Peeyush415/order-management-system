<script setup>
import { reactive, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useProductMutations } from '@/composables/useProducts';
import { useToastStore } from '@/stores/toast';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** { id, name, stock_quantity } */
    product: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const toast = useToastStore();
const { adjustStock } = useProductMutations();
const { error, message, clear, fromResponse } = useFormErrors();
const form = reactive({ quantity: '', reason: '' });

// Start empty every time the modal opens.
watch(
    () => props.open,
    (open) => {
        if (open) {
            form.quantity = '';
            form.reason = '';
            clear();
        }
    },
);

async function submit() {
    clear();
    try {
        const updated = await adjustStock.mutateAsync({
            id: props.product.id,
            payload: { quantity: Number(form.quantity), reason: form.reason },
        });
        toast.success(`Stock is now ${updated.stock_quantity}`);
        emit('close');
    } catch (e) {
        // A negative adjustment below zero comes back as a business error, not a field error.
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}
</script>

<template>
    <BaseModal :open="open" :title="`Adjust stock — ${product.name}`" size="sm" @close="emit('close')">
        <form id="stock-adjust-form" class="space-y-4" novalidate @submit.prevent="submit">
            <p class="text-sm text-gray-600">Current stock: <span class="font-medium">{{ product.stock_quantity }}</span></p>
            <BaseInput v-model="form.quantity" type="number" step="1" label="Quantity" hint="Positive to add (goods received), negative to remove (damaged, count fix)." :error="error('quantity')" />
            <BaseInput v-model.trim="form.reason" label="Reason" :error="error('reason')" />
        </form>
        <template #footer>
            <BaseButton variant="secondary" :disabled="adjustStock.isPending.value" @click="emit('close')">Cancel</BaseButton>
            <BaseButton type="submit" form="stock-adjust-form" :loading="adjustStock.isPending.value">Apply</BaseButton>
        </template>
    </BaseModal>
</template>
