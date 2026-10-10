<script setup>
import { computed, reactive, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useCustomer, useCustomerMutations } from '@/composables/useCustomers';
import { useFormErrors } from '@/composables/useFormErrors';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

/** Same page for create (/customers/new) and edit (/customers/:id/edit). */
const id = computed(() => route.params.id ?? '');
const isEdit = computed(() => !!id.value);

const fields = ['name', 'email', 'phone', 'company', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country'];
const form = reactive(Object.fromEntries(fields.map((field) => [field, ''])));

const { data: customer, isPending: loading, isError: loadFailed, refetch } = useCustomer(id);
watch(
    customer,
    (value) => {
        if (value) {
            fields.forEach((field) => (form[field] = value[field] ?? ''));
        }
    },
    { immediate: true },
);

const { create, update } = useCustomerMutations();
const { error, message, clear, fromResponse } = useFormErrors();
const saving = computed(() => create.isPending.value || update.isPending.value);

async function submit() {
    clear();
    try {
        const saved = isEdit.value
            ? await update.mutateAsync({ id: id.value, payload: { ...form } })
            : await create.mutateAsync({ ...form });

        toast.success(isEdit.value ? 'Customer updated' : 'Customer created');
        router.push({ name: 'customers.show', params: { id: saved.id } });
    } catch (e) {
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}
</script>

<template>
    <div class="max-w-3xl">
        <SkeletonLoader v-if="isEdit && loading" :lines="6" />
        <ErrorState v-else-if="isEdit && loadFailed" title="Unable to load this customer." @retry="refetch()" />

        <form v-else class="space-y-4" novalidate @submit.prevent="submit">
            <BaseCard :title="isEdit ? 'Edit customer' : 'New customer'">
                <div class="grid gap-4 sm:grid-cols-2">
                    <BaseInput v-model.trim="form.name" label="Name" :error="error('name')" autocomplete="off" required />
                    <BaseInput v-model.trim="form.email" label="Email" type="email" :error="error('email')" required />
                    <BaseInput v-model.trim="form.phone" label="Phone" :error="error('phone')" />
                    <BaseInput v-model.trim="form.company" label="Company" :error="error('company')" />
                </div>
            </BaseCard>

            <BaseCard title="Address">
                <div class="grid gap-4 sm:grid-cols-2">
                    <BaseInput v-model.trim="form.address_line1" label="Address line 1" :error="error('address_line1')" class="sm:col-span-2" required />
                    <BaseInput v-model.trim="form.address_line2" label="Address line 2" :error="error('address_line2')" class="sm:col-span-2" />
                    <BaseInput v-model.trim="form.city" label="City" :error="error('city')" required />
                    <BaseInput v-model.trim="form.state" label="State" :error="error('state')" />
                    <BaseInput v-model.trim="form.postal_code" label="Postal code" :error="error('postal_code')" />
                    <BaseInput v-model.trim="form.country" label="Country" :error="error('country')" required />
                </div>
            </BaseCard>

            <div class="flex justify-end gap-2">
                <BaseButton variant="secondary" :disabled="saving" @click="router.back()">Cancel</BaseButton>
                <BaseButton type="submit" :loading="saving">{{ isEdit ? 'Save changes' : 'Create customer' }}</BaseButton>
            </div>
        </form>
    </div>
</template>
