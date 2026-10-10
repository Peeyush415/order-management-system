<script setup>
import { useOrderForm } from '@/composables/useOrderForm';
import { formatBps, formatMoney } from '@/utils/money';

const { form, pricing } = useOrderForm();
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="py-1 pr-3">Product</th>
                    <th class="py-1 pr-3 text-right">Qty</th>
                    <th class="py-1 pr-3 text-right">Subtotal</th>
                    <th class="py-1 pr-3 text-right">Discount</th>
                    <th class="py-1 pr-3 text-right">Tax</th>
                    <th class="py-1 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr v-for="line in pricing.lines" :key="line.product_id">
                    <td class="py-1.5 pr-3">{{ line.name }}</td>
                    <td class="py-1.5 pr-3 text-right">{{ line.quantity }}</td>
                    <td class="py-1.5 pr-3 text-right">${{ formatMoney(line.line_subtotal) }}</td>
                    <td class="py-1.5 pr-3 text-right">{{ line.discount_amount ? `−$${formatMoney(line.discount_amount)}` : '—' }}</td>
                    <td class="py-1.5 pr-3 text-right">${{ formatMoney(line.tax_amount) }} <span class="text-xs text-gray-400">({{ formatBps(line.tax_rate_bps) }})</span></td>
                    <td class="py-1.5 text-right font-medium">${{ formatMoney(line.line_total) }}</td>
                </tr>
            </tbody>
        </table>
        <dl class="mt-3 ml-auto max-w-xs space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>${{ formatMoney(pricing.subtotal) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd>−${{ formatMoney(pricing.discount_total) }}</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd>${{ formatMoney(pricing.tax_total) }}</dd></div>
            <div class="flex justify-between border-t border-gray-200 pt-1 text-base font-semibold">
                <dt>Grand total</dt><dd data-test="grand-total">${{ formatMoney(pricing.grand_total) }}</dd>
            </div>
        </dl>
        <p v-if="form.version" class="mt-2 text-xs text-gray-500">The server recalculates all amounts with current product prices when you save.</p>
        <p v-else class="mt-2 text-xs text-gray-500">Preview only — the server calculates the final amounts.</p>
    </div>
</template>
