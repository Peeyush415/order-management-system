<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useProducts } from '@/composables/useProducts';

/** Search active products on the server and add them to an order. */
const props = defineProps({
    /** Product ids already on the order; their "Add" button is disabled. */
    selectedIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const term = ref('');
const debounced = ref('');
let timer = null;
watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => (debounced.value = value.trim()), 300);
});
onBeforeUnmount(() => clearTimeout(timer));

const filters = computed(() => ({ search: debounced.value, status: 'active', sort: 'name', direction: 'asc', per_page: 8 }));
const { data, isPending, isError } = useProducts(filters);
const chosen = computed(() => new Set(props.selectedIds));
</script>

<template>
    <div class="rounded-lg border border-gray-200">
        <div class="border-b border-gray-200 p-3">
            <label for="product-search" class="sr-only">Search products</label>
            <input
                id="product-search"
                v-model="term"
                type="search"
                placeholder="Search products by name or SKU…"
                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
            />
        </div>
        <p v-if="isPending" class="p-3 text-sm text-gray-500">Loading products…</p>
        <p v-else-if="isError" class="p-3 text-sm text-red-600">Unable to load products.</p>
        <p v-else-if="!data?.data?.length" class="p-3 text-sm text-gray-500">No products found.</p>
        <ul v-else class="max-h-72 divide-y divide-gray-100 overflow-y-auto">
            <li v-for="product in data.data" :key="product.id" class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-900">{{ product.name }}</p>
                    <p class="text-xs text-gray-500">
                        <span class="font-mono">{{ product.sku }}</span> · ${{ product.price_formatted }} · {{ product.stock_quantity }} in stock
                    </p>
                </div>
                <BaseButton size="sm" variant="secondary" :disabled="chosen.has(product.id)" @click="emit('select', product)">
                    {{ chosen.has(product.id) ? 'Added' : 'Add' }}
                </BaseButton>
            </li>
        </ul>
    </div>
</template>
