<script setup>
import { ref } from 'vue';
import AppHeader from './AppHeader.vue';
import AppSidebar from './AppSidebar.vue';

const sidebarOpen = ref(false);
</script>

<template>
    <div class="flex min-h-screen bg-gray-50 text-gray-900">
        <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
        <div class="flex min-w-0 flex-1 flex-col">
            <AppHeader @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <main class="flex-1 p-4 md:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
