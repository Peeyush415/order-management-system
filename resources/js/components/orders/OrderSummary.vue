<script setup>
import PriorityBadge from '@/components/PriorityBadge.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import { formatDate } from '@/utils/date';

defineProps({
    order: { type: Object, required: true },
});
</script>

<template>
    <BaseCard title="Order summary">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
            <div><dt class="text-gray-500">Order number</dt><dd class="font-medium">{{ order.order_number }}</dd></div>
            <div><dt class="text-gray-500">Customer</dt><dd>{{ order.customer.name }}</dd></div>
            <div><dt class="text-gray-500">Order date</dt><dd>{{ formatDate(order.order_date) }}</dd></div>
            <div><dt class="text-gray-500">Total</dt><dd class="text-lg font-semibold">${{ order.formatted.grand_total }}</dd></div>
            <div><dt class="text-gray-500">Status</dt><dd><StatusBadge :status="order.status" /></dd></div>
            <div><dt class="text-gray-500">Priority</dt><dd><PriorityBadge :priority="order.priority" /></dd></div>
            <div><dt class="text-gray-500">Assigned to</dt><dd>{{ order.assignee?.name ?? 'Unassigned' }}</dd></div>
            <div><dt class="text-gray-500">Created by</dt><dd>{{ order.creator?.name ?? '—' }}</dd></div>
            <div v-if="order.cancellation_reason" class="col-span-2 sm:col-span-4">
                <dt class="text-gray-500">Cancellation reason</dt>
                <dd>{{ order.cancellation_reason }}</dd>
            </div>
        </dl>
    </BaseCard>
</template>
