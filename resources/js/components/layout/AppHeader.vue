<script setup>
import { useQueryClient } from '@tanstack/vue-query';
import { useRoute, useRouter } from 'vue-router';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import NotificationBell from './NotificationBell.vue';
import { useAuthStore } from '@/stores/auth';

defineEmits(['toggle-sidebar']);

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const queryClient = useQueryClient();

async function logout() {
    try {
        await auth.logout();
    } finally {
        // Drop cached server data so the next user never sees it.
        queryClient.clear();
        await router.push({ name: 'login' });
    }
}
</script>

<template>
    <header class="flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 md:px-6">
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="rounded p-2 text-gray-600 hover:bg-gray-100 md:hidden"
                aria-label="Open menu"
                @click="$emit('toggle-sidebar')"
            >
                ☰
            </button>
            <h1 class="text-base font-semibold text-gray-900">{{ route.meta.title ?? '' }}</h1>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <NotificationBell />
            <span class="hidden sm:inline">{{ auth.user?.name }}</span>
            <BaseBadge v-for="role in auth.user?.roles ?? []" :key="role" color="indigo">{{ role }}</BaseBadge>
            <BaseButton variant="secondary" size="sm" @click="logout">Logout</BaseButton>
        </div>
    </header>
</template>
