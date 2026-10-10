<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppLayout from '@/components/layout/AppLayout.vue';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();

// Pages like login and "not found" render without the sidebar and header.
const withLayout = computed(() => auth.isAuthenticated && route.meta.layout !== false);
</script>

<template>
    <AppLayout v-if="withLayout">
        <RouterView />
    </AppLayout>
    <main v-else class="min-h-screen bg-gray-50 text-gray-900">
        <RouterView />
    </main>
    <ToastContainer />
</template>
