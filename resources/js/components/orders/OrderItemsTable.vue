<script setup>
import BaseCard from '@/components/ui/BaseCard.vue';
import DataTable from '@/components/ui/DataTable.vue';
import { formatBps } from '@/utils/money';

defineProps({
    /** Order with items and totals (amounts as cents plus `formatted` strings). */
    order: { type: Object, required: true },
});

const columns = [
    { key: 'product_name', label: 'Product' },
    { key: 'sku', label: 'SKU', class: 'hidden sm:table-cell' },
    { key: 'quantity', label: 'Qty', class: 'text-right' },
    { key: 'unit_price', label: 'Unit price', class: 'text-right' },
    { key: 'discount_amount', label: 'Discount', class: 'text-right' },
    { key: 'tax_amount', label: 'Tax', class: 'text-right' },
    { key: 'line_total', label: 'Total', class: 'text-right' },
];
</script>

<template>
    <BaseCard title="Order items">
        <DataTable :columns="columns" :rows="order.items" empty-title="No items.">
            <template #cell-sku="{ value }"><span class="font-mono text-xs">{{ value }}</span></template>
            <template #cell-unit_price="{ row }">${{ row.formatted.unit_price }}</template>
            <template #cell-discount_amount="{ row }">{{ row.discount_amount ? `−$${row.formatted.discount_amount}` : '—' }}</template>
            <template #cell-tax_amount="{ row }">
                ${{ row.formatted.tax_amount }} <span class="text-xs text-gray-400">({{ formatBps(row.tax_rate_bps) }})</span>
            </template>
            <template #cell-line_total="{ row }"><span class="font-medium">${{ row.formatted.line_total }}</span></template>
        </DataTable>

        <dl class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>${{ order.formatted.subtotal }}</dd></div>
            <div class="flex justify-between">
                <dt class="text-gray-500">
                    Discount
                    <span v-if="order.discount_type === 'percent'" class="text-xs">({{ formatBps(order.discount_value) }})</span>
                </dt>
                <dd>−${{ order.formatted.discount_total }}</dd>
            </div>
            <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd>${{ order.formatted.tax_total }}</dd></div>
            <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold">
                <dt>Grand total</dt><dd>${{ order.formatted.grand_total }}</dd>
            </div>
        </dl>
    </BaseCard>
</template>
