<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import BaseButton from '@/components/ui/BaseButton.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { notificationLink, useNotificationList, useNotificationMutations, useUnreadCount } from '@/composables/useNotifications';
import { useListFilters } from '@/composables/useListFilters';
import { useAuthStore } from '@/stores/auth';
import { formatDateTime } from '@/utils/date';

const router = useRouter();
const auth = useAuthStore();

const { filters, update } = useListFilters({ unread: '', page: 1 });
const params = computed(() => ({ page: filters.value.page, ...(filters.value.unread ? { unread: 1 } : {}) }));
const { data, isPending, isError, refetch } = useNotificationList(params);
const { data: count } = useUnreadCount();
const { markRead, markAllRead } = useNotificationMutations();

const tabs = [
    { value: '', label: 'All' },
    { value: '1', label: 'Unread' },
];

function open(notification) {
    if (!notification.read) {
        markRead.mutate(notification.id);
    }
    const link = notificationLink(notification, { canManageProducts: auth.can('products.manage') });
    if (link) {
        router.push(link);
    }
}
</script>

<template>
    <div class="max-w-3xl space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-1" role="tablist" aria-label="Show">
                <BaseButton
                    v-for="tab in tabs"
                    :key="tab.value"
                    role="tab"
                    size="sm"
                    :variant="filters.unread === tab.value ? 'primary' : 'ghost'"
                    :aria-selected="filters.unread === tab.value"
                    @click="update({ unread: tab.value })"
                >
                    {{ tab.label }}<template v-if="tab.value && count"> ({{ count }})</template>
                </BaseButton>
            </div>
            <BaseButton variant="secondary" size="sm" :disabled="!count" :loading="markAllRead.isPending.value" @click="markAllRead.mutate()">
                Mark all as read
            </BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <div v-if="isPending" class="p-4"><SkeletonLoader :lines="6" /></div>
            <ErrorState v-else-if="isError" title="Unable to load notifications." @retry="refetch()" />
            <EmptyState
                v-else-if="!data.data.length"
                :title="filters.unread ? 'No unread notifications.' : 'No notifications yet.'"
                message="You'll see order assignments, status changes and stock alerts here."
            />
            <template v-else>
                <ul class="divide-y divide-gray-100">
                    <li v-for="notification in data.data" :key="notification.id" class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                        <button type="button" class="flex flex-1 gap-2 text-left" @click="open(notification)">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="notification.read ? 'bg-transparent' : 'bg-blue-600'" aria-hidden="true" />
                            <span>
                                <span class="block" :class="notification.read ? 'text-gray-500' : 'font-medium text-gray-900'">{{ notification.message }}</span>
                                <span class="block text-xs text-gray-400">{{ formatDateTime(notification.created_at) }}</span>
                            </span>
                        </button>
                        <BaseButton v-if="!notification.read" variant="ghost" size="sm" @click="markRead.mutate(notification.id)">Mark as read</BaseButton>
                    </li>
                </ul>
                <Pagination v-if="data.meta.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
