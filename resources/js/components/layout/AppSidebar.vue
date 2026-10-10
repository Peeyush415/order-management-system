<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { navigation, visibleNavigation } from '@/config/navigation';
import { useAuthStore } from '@/stores/auth';

defineProps({
    /** Mobile only: the sidebar slides in over the page. */
    open: { type: Boolean, default: false },
});

defineEmits(['close']);

const auth = useAuthStore();
const router = useRouter();

const links = computed(() =>
    visibleNavigation(navigation, {
        can: (permission) => auth.can(permission),
        hasRoute: (name) => router.hasRoute(name),
    }),
);
</script>

<template>
    <!-- Mobile backdrop -->
    <div v-if="open" class="fixed inset-0 z-20 bg-black/30 md:hidden" @click="$emit('close')" />

    <aside
        class="fixed inset-y-0 left-0 z-30 w-60 transform border-r border-gray-200 bg-white transition-transform md:static md:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-14 items-center border-b border-gray-200 px-5">
            <RouterLink :to="{ name: 'home' }" class="text-lg font-semibold text-gray-900" @click="$emit('close')">OMS</RouterLink>
        </div>
        <nav class="space-y-1 p-3" aria-label="Main">
            <RouterLink
                v-for="link in links"
                :key="link.route"
                :to="{ name: link.route }"
                class="block rounded px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100"
                active-class="bg-blue-50 text-blue-700 hover:bg-blue-50"
                @click="$emit('close')"
            >
                {{ link.label }}
            </RouterLink>
        </nav>
    </aside>
</template>
