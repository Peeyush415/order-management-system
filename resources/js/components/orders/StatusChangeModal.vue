<script setup>
import { computed, ref, toRef, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useOrderMutations } from '@/composables/useOrder';
import { useToastStore } from '@/stores/toast';

const props = defineProps({
    open: { type: Boolean, default: false },
    orderId: { type: String, required: true },
    /** From allowed-transitions: [{ value, label, requires_remarks }] — only moves this user may make. */
    transitions: { type: Array, default: () => [] },
    /** Pre-selected target, e.g. 'cancelled' for the "Cancel order" button. */
    preset: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const toast = useToastStore();
const { changeStatus } = useOrderMutations(toRef(props, 'orderId'));
const { error, message, clear, fromResponse } = useFormErrors();

const status = ref('');
const remarks = ref('');
const generalError = ref('');

watch(
    () => props.open,
    (open) => {
        if (open) {
            status.value = props.preset || props.transitions[0]?.value || '';
            remarks.value = '';
            generalError.value = '';
            clear();
        }
    },
    { immediate: true },
);

const selected = computed(() => props.transitions.find((t) => t.value === status.value));
const options = computed(() => props.transitions.map((t) => ({ value: t.value, label: t.label })));
// Mirrors the API rule so the user sees it before submitting; the API still enforces it.
const remarksRequired = computed(() => !!selected.value?.requires_remarks);
const canSubmit = computed(() => !!selected.value && (!remarksRequired.value || remarks.value.trim() !== ''));

async function submit() {
    clear();
    generalError.value = '';
    try {
        await changeStatus.mutateAsync({ status: status.value, remarks: remarks.value.trim() || null });
        toast.success(`Status changed to ${selected.value.label}`);
        emit('close');
    } catch (e) {
        fromResponse(e);
        // e.g. "Insufficient stock for Wireless Keyboard: 3 available, 5 requested."
        generalError.value = error('items') || error('status') || (error('remarks') ? '' : message.value);
    }
}
</script>

<template>
    <BaseModal :open="open" title="Change status" size="sm" @close="emit('close')">
        <form id="status-form" class="space-y-4" novalidate @submit.prevent="submit">
            <p v-if="generalError" class="rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ generalError }}</p>
            <BaseSelect v-model="status" label="New status" :options="options" />
            <div>
                <label for="status-remarks" class="mb-1 block text-sm font-medium text-gray-700">
                    {{ remarksRequired ? 'Reason (required)' : 'Comment (optional)' }}
                </label>
                <textarea
                    id="status-remarks"
                    v-model="remarks"
                    rows="3"
                    :placeholder="remarksRequired ? 'Why is this order being cancelled?' : 'e.g. Customer confirmed the order.'"
                    class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                    :class="error('remarks') ? 'border-red-500' : 'border-gray-300'"
                />
                <p v-if="error('remarks')" class="mt-1 text-sm text-red-600">{{ error('remarks') }}</p>
            </div>
        </form>
        <template #footer>
            <BaseButton variant="secondary" :disabled="changeStatus.isPending.value" @click="emit('close')">Close</BaseButton>
            <BaseButton
                type="submit"
                form="status-form"
                :variant="status === 'cancelled' ? 'danger' : 'primary'"
                :disabled="!canSubmit"
                :loading="changeStatus.isPending.value"
            >
                {{ status === 'cancelled' ? 'Cancel order' : 'Change status' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>
