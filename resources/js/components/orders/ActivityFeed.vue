<script setup>
import { computed, toRef } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useOrderActivities } from '@/composables/useOrder';
import { formatDateTime } from '@/utils/date';

const props = defineProps({
    orderId: { type: String, required: true },
});

const { data, isPending, isError, refetch, hasNextPage, fetchNextPage, isFetchingNextPage } = useOrderActivities(toRef(props, 'orderId'));

const activities = computed(() => data.value?.pages.flatMap((page) => page.data) ?? []);
</script>

<template>
    <BaseCard title="Activity">
        <SkeletonLoader v-if="isPending" :lines="4" />
        <ErrorState v-else-if="isError" title="Unable to load activity." @retry="refetch()" />
        <EmptyState v-else-if="!activities.length" title="No activity yet." />
        <template v-else>
            <ul class="divide-y divide-gray-100">
                <li v-for="activity in activities" :key="activity.id" class="py-2 text-sm">
                    <p class="text-gray-900">{{ activity.description }}</p>
                    <p class="text-xs text-gray-500">{{ activity.user?.name ?? 'System' }} · {{ formatDateTime(activity.created_at) }}</p>
                </li>
            </ul>
            <BaseButton v-if="hasNextPage" variant="ghost" size="sm" class="mt-2" :loading="isFetchingNextPage" @click="fetchNextPage()">
                Load more
            </BaseButton>
        </template>
    </BaseCard>
</template>
