<script setup>
import { computed, ref, toRef, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useLookups } from '@/composables/useLookups';
import { useOrderMutations } from '@/composables/useOrder';
import { useToastStore } from '@/stores/toast';

const props = defineProps({
    open: { type: Boolean, default: false },
    orderId: { type: String, required: true },
    /** Current assignee id, or null. */
    current: { type: [Number, null], default: null },
});

const emit = defineEmits(['close']);

const toast = useToastStore();
const { data: lookups } = useLookups();
const { assign } = useOrderMutations(toRef(props, 'orderId'));
const { error, message, clear, fromResponse } = useFormErrors();

const UNASSIGNED = 'none';
const choice = ref(UNASSIGNED);
watch(
    () => props.open,
    (open) => {
        if (open) {
            choice.value = props.current ? String(props.current) : UNASSIGNED;
            clear();
        }
    },
    { immediate: true },
);

const options = computed(() => [
    { value: UNASSIGNED, label: 'Unassigned' },
    ...(lookups.value?.assignable_users ?? []).map((u) => ({ value: String(u.id), label: u.name })),
]);

async function submit() {
    clear();
    try {
        const assignee = choice.value === UNASSIGNED ? null : Number(choice.value);
        await assign.mutateAsync(assignee);
        toast.success(assignee ? `Assigned to ${options.value.find((o) => o.value === choice.value)?.label}` : 'Order unassigned');
        emit('close');
    } catch (e) {
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}
</script>

<template>
    <BaseModal :open="open" title="Assign order" size="sm" @close="emit('close')">
        <form id="assign-form" novalidate @submit.prevent="submit">
            <BaseSelect v-model="choice" label="Assign to" :options="options" :error="error('assigned_to')" />
        </form>
        <template #footer>
            <BaseButton variant="secondary" :disabled="assign.isPending.value" @click="emit('close')">Close</BaseButton>
            <BaseButton type="submit" form="assign-form" :loading="assign.isPending.value">Save</BaseButton>
        </template>
    </BaseModal>
</template>
