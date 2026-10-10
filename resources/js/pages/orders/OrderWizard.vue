<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import StepAssignment from '@/components/orders/wizard/StepAssignment.vue';
import StepCustomer from '@/components/orders/wizard/StepCustomer.vue';
import StepPricing from '@/components/orders/wizard/StepPricing.vue';
import StepProducts from '@/components/orders/wizard/StepProducts.vue';
import StepReview from '@/components/orders/wizard/StepReview.vue';
import WizardSteps from '@/components/orders/wizard/WizardSteps.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useOrder, useOrderFormMutations } from '@/composables/useOrder';
import { provideOrderForm, STEPS, stepForField } from '@/composables/useOrderForm';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

/** Same page for /orders/new and /orders/:id/edit. */
const id = computed(() => (route.params.id ? String(route.params.id) : ''));
const isEdit = computed(() => !!id.value);

const { form, errors, fill, validateStep, toPayload } = provideOrderForm({ mode: isEdit.value ? 'edit' : 'create' });
const { create, update } = useOrderFormMutations();

// Edit: load the order once and fill the form; "Reload" after a conflict fills it again.
const { data: order, isPending: loading, isError: loadFailed, refetch } = useOrder(id);
let filled = false;
watch(
    order,
    (value) => {
        if (value && !filled) {
            fill(value);
            filled = true;
        }
    },
    { immediate: true },
);
// Only draft and pending orders can change items, customer and pricing.
const notEditable = computed(() => isEdit.value && order.value && !['draft', 'pending'].includes(order.value.status.value));

const step = ref(0);
const reached = ref(0);
const conflict = ref(false);
const saving = computed(() => create.isPending.value || update.isPending.value);
const components = [StepCustomer, StepProducts, StepPricing, StepAssignment, StepReview];

function next() {
    if (validateStep(step.value)) {
        step.value += 1;
        reached.value = Math.max(reached.value, step.value);
    }
}

function goTo(index) {
    if (index <= reached.value) {
        errors.value = {};
        step.value = index;
    }
}

async function submit() {
    errors.value = {};
    conflict.value = false;
    try {
        const saved = isEdit.value ? await update.mutateAsync({ id: id.value, payload: toPayload() }) : await create.mutateAsync(toPayload());
        toast.success(isEdit.value ? `Order ${saved.order_number} updated` : `Order ${saved.order_number} created`);
        router.push({ name: 'orders.show', params: { id: saved.id } });
    } catch (e) {
        const response = e.response;
        if (response?.status === 409) {
            conflict.value = true;
        } else if (response?.status === 422 && response.data?.errors) {
            // Show each server error on the step that owns the field.
            errors.value = response.data.errors;
            step.value = Math.min(...Object.keys(response.data.errors).map(stepForField));
        } else {
            toast.error(response?.data?.message ?? 'Could not save the order. Please try again.');
        }
    }
}

async function reload() {
    const { data } = await refetch();
    if (data) {
        fill(data);
    }
    conflict.value = false;
    toast.info('Loaded the latest version of the order. Check your changes and save again.');
}
</script>

<template>
    <SkeletonLoader v-if="isEdit && loading" :lines="8" />
    <div v-else-if="isEdit && loadFailed" class="rounded-lg border border-gray-200 bg-white">
        <ErrorState title="Unable to load this order." @retry="refetch()" />
    </div>
    <BaseCard v-else-if="notEditable" title="This order can no longer be edited">
        <p class="text-sm text-gray-600">
            {{ order.order_number }} is {{ order.status.label.toLowerCase() }}. Items, customer and pricing can only change while an order is draft or pending.
        </p>
        <BaseButton class="mt-4" variant="secondary" @click="router.push({ name: 'orders.show', params: { id: order.id } })">Back to the order</BaseButton>
    </BaseCard>

    <div v-else class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold text-gray-900">{{ isEdit ? `Edit ${order?.order_number ?? ''}` : 'New order' }}</h2>
            <WizardSteps :steps="STEPS" :current="step" :reached="reached" @go="goTo" />
        </div>

        <div v-if="conflict" class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900" role="alert">
            <span>This order was changed by someone else while you were editing it.</span>
            <BaseButton size="sm" variant="secondary" @click="reload">Reload latest version</BaseButton>
        </div>

        <BaseCard :title="`${step + 1}. ${STEPS[step]}`">
            <component :is="components[step]" />
        </BaseCard>

        <div class="flex justify-between">
            <BaseButton v-if="step > 0" variant="secondary" :disabled="saving" @click="goTo(step - 1)">Back</BaseButton>
            <span v-else />
            <BaseButton v-if="step < STEPS.length - 1" @click="next">Next</BaseButton>
            <BaseButton v-else :loading="saving" :disabled="!form.items.length" @click="submit">{{ isEdit ? 'Save changes' : 'Create order' }}</BaseButton>
        </div>
    </div>
</template>
