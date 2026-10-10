<script setup>
import { computed } from 'vue';
import CustomerPicker from '@/components/customers/CustomerPicker.vue';
import { useCustomer } from '@/composables/useCustomers';
import { useOrderForm } from '@/composables/useOrderForm';

const { form, errors } = useOrderForm();

const selectedId = computed(() => (form.customer_id ? String(form.customer_id) : ''));
const { data: customer } = useCustomer(selectedId);
</script>

<template>
    <div class="space-y-4">
        <!-- Only active customers can receive new orders. -->
        <CustomerPicker v-model="form.customer_id" label="Customer" :filters="{ status: 'active' }" :error="errors.customer_id?.[0] ?? ''" />

        <div v-if="customer" class="rounded-lg bg-gray-50 p-4 text-sm">
            <p class="font-medium text-gray-900">{{ customer.name }}</p>
            <p class="text-gray-600">{{ customer.email }}<template v-if="customer.phone"> · {{ customer.phone }}</template></p>
            <p class="mt-1 text-gray-600">
                {{ customer.address_line1 }}, {{ [customer.city, customer.state, customer.postal_code].filter(Boolean).join(', ') }}, {{ customer.country }}
            </p>
        </div>
    </div>
</template>
