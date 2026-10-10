<script setup>
import StatusBadge from '@/components/StatusBadge.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { formatDateTime } from '@/utils/date';

defineProps({
    /** Status history, oldest first. */
    history: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: Boolean, default: false },
});

defineEmits(['retry']);
</script>

<template>
    <BaseCard title="Timeline">
        <SkeletonLoader v-if="loading" :lines="4" />
        <ErrorState v-else-if="error" title="Unable to load the timeline." @retry="$emit('retry')" />
        <ol v-else class="relative space-y-5 border-l border-gray-200 pl-5">
            <li v-for="entry in history" :key="entry.id" class="relative">
                <span class="absolute top-1.5 -left-[25px] h-2.5 w-2.5 rounded-full bg-blue-500 ring-4 ring-white" aria-hidden="true" />
                <p class="flex flex-wrap items-center gap-2 text-sm">
                    <template v-if="entry.from">
                        <StatusBadge :status="entry.from" /> <span aria-hidden="true">→</span>
                        <span class="sr-only">to</span>
                        <StatusBadge :status="entry.to" />
                    </template>
                    <template v-else>
                        <span class="font-medium">Order created</span> as <StatusBadge :status="entry.to" />
                    </template>
                </p>
                <p class="mt-1 text-xs text-gray-500">{{ entry.changed_by?.name ?? 'System' }} · {{ formatDateTime(entry.created_at) }}</p>
                <p v-if="entry.remarks" class="mt-1 rounded bg-gray-50 px-2 py-1 text-sm text-gray-700">“{{ entry.remarks }}”</p>
            </li>
        </ol>
    </BaseCard>
</template>
