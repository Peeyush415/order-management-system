<script setup>
import { computed } from 'vue';
import ProductPicker from '@/components/products/ProductPicker.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useOrderForm } from '@/composables/useOrderForm';
import { formatMoney } from '@/utils/money';

const { form, errors, pricing, addProduct, removeLine } = useOrderForm();

const selectedIds = computed(() => form.items.map((line) => line.product_id));
const lineError = (index, field) => errors.value[`items.${index}.${field}`]?.[0] ?? '';
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-2">
        <ProductPicker :selected-ids="selectedIds" @select="addProduct" />

        <div>
            <p v-if="errors.items" class="mb-2 text-sm text-red-600">{{ errors.items[0] }}</p>
            <p v-if="!form.items.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                No products yet. Search on the left and click “Add”.
            </p>
            <ul v-else class="divide-y divide-gray-100 rounded-lg border border-gray-200">
                <li v-for="(line, index) in form.items" :key="line.product_id" class="space-y-1 p-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-900">{{ line.name }}</p>
                            <p class="text-xs text-gray-500"><span class="font-mono">{{ line.sku }}</span> · ${{ formatMoney(line.unit_price) }} each</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <label :for="`qty-${line.product_id}`" class="sr-only">Quantity for {{ line.name }}</label>
                            <input
                                :id="`qty-${line.product_id}`"
                                v-model.number="line.quantity"
                                type="number"
                                min="1"
                                step="1"
                                class="w-20 rounded border px-2 py-1 text-right"
                                :class="lineError(index, 'quantity') ? 'border-red-500' : 'border-gray-300'"
                            />
                            <span class="w-24 text-right font-medium">${{ formatMoney(pricing.lines[index]?.line_subtotal ?? 0) }}</span>
                            <BaseButton variant="ghost" size="sm" :aria-label="`Remove ${line.name}`" @click="removeLine(index)">✕</BaseButton>
                        </div>
                    </div>
                    <p v-if="lineError(index, 'quantity')" class="text-red-600">{{ lineError(index, 'quantity') }}</p>
                    <p v-if="lineError(index, 'product_id')" class="text-red-600">{{ lineError(index, 'product_id') }}</p>
                    <!-- A warning, not a block: stock is only deducted when the order is confirmed. -->
                    <p v-if="line.stock_quantity !== null && line.quantity > line.stock_quantity" class="text-orange-700">
                        Only {{ line.stock_quantity }} in stock — confirming this order will fail until stock is added.
                    </p>
                </li>
            </ul>
        </div>
    </div>
</template>
