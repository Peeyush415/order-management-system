<script setup>
import { useQueryClient } from '@tanstack/vue-query';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
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
    <div class="min-h-screen bg-gray-50 text-gray-900">
        <!-- Minimal header for now; the full layout with sidebar comes later. -->
        <header v-if="auth.isAuthenticated" class="flex items-center justify-between border-b border-gray-200 bg-white px-6 py-3">
            <RouterLink to="/" class="font-semibold">OMS</RouterLink>
            <div class="flex items-center gap-4 text-sm">
                <span>
                    {{ auth.user.name }}
                    <span class="ml-1 rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ auth.user.roles.join(', ') }}</span>
                </span>
                <button type="button" class="rounded border border-gray-300 px-3 py-1 hover:bg-gray-100" @click="logout">
                    Logout
                </button>
            </div>
        </header>

        <main>
            <RouterView />
        </main>
    </div>
</template>
