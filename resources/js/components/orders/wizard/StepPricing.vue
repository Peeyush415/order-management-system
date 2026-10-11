<script setup>
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import { useOrderForm } from '@/composables/useOrderForm';
import PricingBreakdown from './PricingBreakdown.vue';

const { form, errors } = useOrderForm();

const discountTypes = [
    { value: '', label: 'No discount' },
    { value: 'fixed', label: 'Fixed amount ($)' },
    { value: 'percent', label: 'Percentage (%)' },
];
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <BaseSelect v-model="form.discount_type" label="Discount" :options="discountTypes" :error="errors.discount_type?.[0] ?? ''" />
            <BaseInput
                v-if="form.discount_type"
                v-model.trim="form.discount_input"
                :label="form.discount_type === 'fixed' ? 'Amount ($)' : 'Percent (%)'"
                inputmode="decimal"
                :placeholder="form.discount_type === 'fixed' ? '20.00' : '10'"
                :error="errors.discount_value?.[0] ?? ''"
            />
        </div>
        <PricingBreakdown />
    </div>
</template>
