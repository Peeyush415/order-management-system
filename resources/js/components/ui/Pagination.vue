<script setup>
import { computed } from 'vue';
import BaseButton from './BaseButton.vue';

const props = defineProps({
    /** The API's pagination meta: { current_page, per_page, total, last_page }. */
    meta: { type: Object, required: true },
});

const emit = defineEmits(['change']);

const from = computed(() => (props.meta.total === 0 ? 0 : (props.meta.current_page - 1) * props.meta.per_page + 1));
const to = computed(() => Math.min(props.meta.current_page * props.meta.per_page, props.meta.total));

/** At most 5 page numbers around the current page. */
const pages = computed(() => {
    const last = props.meta.last_page;
    const start = Math.max(1, Math.min(props.meta.current_page - 2, last - 4));
    const end = Math.min(last, start + 4);

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

function go(page) {
    if (page >= 1 && page <= props.meta.last_page && page !== props.meta.current_page) {
        emit('change', page);
    }
}
</script>

<template>
    <nav class="flex flex-col items-center justify-between gap-3 px-4 py-3 text-sm sm:flex-row" aria-label="Pagination">
        <p class="text-gray-600">
            Showing <span class="font-medium">{{ from }}</span>–<span class="font-medium">{{ to }}</span> of
            <span class="font-medium">{{ meta.total }}</span>
        </p>
        <div class="flex items-center gap-1">
            <BaseButton variant="secondary" size="sm" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Previous</BaseButton>
            <BaseButton
                v-for="page in pages"
                :key="page"
                size="sm"
                :variant="page === meta.current_page ? 'primary' : 'ghost'"
                :aria-current="page === meta.current_page ? 'page' : undefined"
                @click="go(page)"
            >
                {{ page }}
            </BaseButton>
            <BaseButton variant="secondary" size="sm" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Next</BaseButton>
        </div>
    </nav>
</template>
