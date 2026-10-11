<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useRouter } from 'vue-router';
import { notificationLink, useNotificationList, useNotificationMutations, useUnreadCount } from '@/composables/useNotifications';
import { useAuthStore } from '@/stores/auth';
import { formatDateTime } from '@/utils/date';

const router = useRouter();
const auth = useAuthStore();

const open = ref(false);
const root = ref(null);

const { data: count } = useUnreadCount();
// The latest few, loaded only while the dropdown is open.
const { data: latest, isPending } = useNotificationList({ per_page: 8 }, { enabled: () => open.value });
const { markRead, markAllRead } = useNotificationMutations();

const badge = computed(() => (count.value > 99 ? '99+' : String(count.value ?? 0)));

async function openNotification(notification) {
    if (!notification.read) {
        markRead.mutate(notification.id);
    }
    const link = notificationLink(notification, { canManageProducts: auth.can('products.manage') });
    open.value = false;
    if (link) {
        await router.push(link);
    }
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}
document.addEventListener('click', onDocumentClick);
onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="relative rounded p-2 text-gray-600 hover:bg-gray-100"
            :aria-label="`Notifications, ${count ?? 0} unread`"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span aria-hidden="true">🔔</span>
            <span
                v-if="count"
                class="absolute -top-0.5 -right-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-[10px] leading-5 font-semibold text-white"
                data-test="unread-badge"
            >
                {{ badge }}
            </span>
        </button>

        <div v-if="open" class="absolute right-0 z-30 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-gray-200 bg-white shadow-lg">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2">
                <p class="text-sm font-semibold">Notifications</p>
                <button v-if="count" type="button" class="text-xs text-blue-600 hover:underline" @click="markAllRead.mutate()">Mark all as read</button>
            </div>
            <p v-if="isPending" class="px-4 py-6 text-center text-sm text-gray-500">Loading…</p>
            <p v-else-if="!latest?.data?.length" class="px-4 py-6 text-center text-sm text-gray-500">You're all caught up.</p>
            <ul v-else class="max-h-80 divide-y divide-gray-100 overflow-y-auto">
                <li v-for="notification in latest.data" :key="notification.id">
                    <button
                        type="button"
                        class="flex w-full gap-2 px-4 py-3 text-left text-sm hover:bg-gray-50"
                        :class="notification.read ? 'text-gray-500' : 'text-gray-900'"
                        @click="openNotification(notification)"
                    >
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="notification.read ? 'bg-transparent' : 'bg-blue-600'" aria-hidden="true" />
                        <span>
                            <span class="block" :class="notification.read ? '' : 'font-medium'">{{ notification.message }}</span>
                            <span class="block text-xs text-gray-400">{{ formatDateTime(notification.created_at) }}</span>
                        </span>
                    </button>
                </li>
            </ul>
            <RouterLink :to="{ name: 'notifications' }" class="block border-t border-gray-100 px-4 py-2 text-center text-sm text-blue-600 hover:bg-gray-50" @click="open = false">
                View all
            </RouterLink>
        </div>
    </div>
</template>
