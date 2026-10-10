<script setup>
import { computed } from 'vue';
import { useCustomer } from '@/composables/useCustomers';
import { useLookups } from '@/composables/useLookups';
import { useOrderForm } from '@/composables/useOrderForm';
import { formatDate } from '@/utils/date';
import PricingBreakdown from './PricingBreakdown.vue';

const { mode, form } = useOrderForm();
const { data: lookups } = useLookups();
const { data: customer } = useCustomer(computed(() => (form.customer_id ? String(form.customer_id) : '')));

const priority = computed(() => lookups.value?.order_priorities.find((p) => p.value === Number(form.priority))?.label ?? form.priority);
const assignee = computed(() => lookups.value?.assignable_users.find((u) => String(u.id) === String(form.assigned_to))?.name ?? 'Unassigned');
</script>

<template>
    <div class="space-y-6">
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
            <div><dt class="text-gray-500">Customer</dt><dd class="font-medium">{{ customer?.name ?? '…' }}</dd></div>
            <div><dt class="text-gray-500">Order date</dt><dd>{{ formatDate(form.order_date) }}</dd></div>
            <div><dt class="text-gray-500">Priority</dt><dd>{{ priority }}</dd></div>
            <template v-if="mode === 'create'">
                <div><dt class="text-gray-500">Assigned to</dt><dd>{{ assignee }}</dd></div>
                <div><dt class="text-gray-500">Saved as</dt><dd class="capitalize">{{ form.status }}</dd></div>
                <div v-if="form.note" class="sm:col-span-3"><dt class="text-gray-500">Note</dt><dd class="whitespace-pre-line">{{ form.note }}</dd></div>
            </template>
        </dl>
        <PricingBreakdown />
    </div>
</template>
