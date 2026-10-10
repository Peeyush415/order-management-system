<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useCustomer, useCustomers } from '@/composables/useCustomers';

/**
 * Pick one customer by typing part of their name or email. The list is searched on the
 * server (there may be hundreds of thousands of customers), never loaded in full.
 */
const props = defineProps({
    label: { type: String, default: 'Customer' },
    placeholder: { type: String, default: 'Search customers…' },
    /** Extra filters for the search, e.g. { status: 'active' } in the order form. */
    filters: { type: Object, default: () => ({}) },
    error: { type: String, default: '' },
});

/** The selected customer id ('' when none). */
const model = defineModel({ type: [String, Number], default: '' });

const term = ref('');
const debounced = ref('');
const open = ref(false);
const root = ref(null);

let timer = null;
watch(term, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => (debounced.value = value.trim()), 300);
});

const searchFilters = computed(() => ({ ...props.filters, search: debounced.value, per_page: 8 }));
const { data: results, isFetching } = useCustomers(searchFilters);

// The name of the selected customer, e.g. when the id comes from the URL.
const selectedId = computed(() => (model.value ? String(model.value) : ''));
const { data: selected } = useCustomer(selectedId);

function choose(customer) {
    model.value = customer.id;
    term.value = '';
    open.value = false;
}

function clear() {
    model.value = '';
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}
document.addEventListener('click', onDocumentClick);
onBeforeUnmount(() => {
    clearTimeout(timer);
    document.removeEventListener('click', onDocumentClick);
});
</script>

<template>
    <div ref="root" class="relative">
        <label v-if="label" class="mb-1 block text-sm font-medium text-gray-700">{{ label }}</label>

        <div v-if="selectedId" class="flex items-center justify-between gap-2 rounded border border-gray-300 bg-white px-3 py-2 text-sm">
            <span class="truncate">{{ selected?.name ?? 'Loading…' }}</span>
            <button type="button" class="text-gray-400 hover:text-gray-600" aria-label="Clear customer" @click="clear">✕</button>
        </div>
        <input
            v-else
            v-model="term"
            type="search"
            role="combobox"
            :aria-expanded="open"
            :placeholder="placeholder"
            class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
            :class="error ? 'border-red-500' : 'border-gray-300'"
            @focus="open = true"
            @input="open = true"
        />
        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>

        <ul v-if="open && !selectedId" role="listbox" class="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded border border-gray-200 bg-white py-1 shadow-lg">
            <li v-if="isFetching && !results" class="px-3 py-2 text-sm text-gray-500">Searching…</li>
            <li v-else-if="!results?.data?.length" class="px-3 py-2 text-sm text-gray-500">No customers found.</li>
            <li
                v-for="customer in results?.data ?? []"
                :key="customer.id"
                role="option"
                :aria-selected="false"
                class="cursor-pointer px-3 py-2 text-sm hover:bg-blue-50"
                @click="choose(customer)"
            >
                <span class="font-medium">{{ customer.name }}</span>
                <span class="block text-xs text-gray-500">{{ customer.email }}</span>
            </li>
        </ul>
    </div>
</template>
