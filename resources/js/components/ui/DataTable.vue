<script setup>
import EmptyState from './EmptyState.vue';

defineProps({
    /** [{ key, label, class? }] — a `cell-<key>` slot overrides how a cell renders. */
    columns: { type: Array, required: true },
    rows: { type: Array, default: () => [] },
    rowKey: { type: String, default: 'id' },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'No results found' },
    emptyMessage: { type: String, default: '' },
    /** Shows a pointer and emits row-click. */
    clickable: { type: Boolean, default: false },
});

defineEmits(['row-click']);
</script>

<template>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-2 text-left text-xs font-semibold tracking-wide text-gray-500 uppercase"
                        :class="column.class"
                    >
                        <slot :name="`header-${column.key}`" :column="column">{{ column.label }}</slot>
                    </th>
                </tr>
            </thead>

            <tbody v-if="loading" class="divide-y divide-gray-100">
                <tr v-for="n in 5" :key="n" data-test="skeleton-row">
                    <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                        <div class="h-4 animate-pulse rounded bg-gray-200" />
                    </td>
                </tr>
            </tbody>

            <tbody v-else-if="rows.length" class="divide-y divide-gray-100 bg-white">
                <tr
                    v-for="row in rows"
                    :key="row[rowKey]"
                    :class="clickable ? 'cursor-pointer hover:bg-gray-50' : ''"
                    @click="clickable && $emit('row-click', row)"
                >
                    <td v-for="column in columns" :key="column.key" class="px-4 py-3 whitespace-nowrap" :class="column.class">
                        <slot :name="`cell-${column.key}`" :row="row" :value="row[column.key]">{{ row[column.key] }}</slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState v-if="!loading && !rows.length" :title="emptyTitle" :message="emptyMessage">
            <slot name="empty-action" />
        </EmptyState>
    </div>
</template>
