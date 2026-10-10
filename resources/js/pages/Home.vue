<script setup>
import BaseCard from '@/components/ui/BaseCard.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useHealth } from '@/composables/useHealth';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const { data, isPending, isError, refetch } = useHealth();
</script>

<template>
    <div class="space-y-6">
        <BaseCard>
            <p class="text-lg font-medium text-gray-900">Welcome back, {{ auth.user?.name }}</p>
            <p class="mt-1 text-sm text-gray-500">Use the menu to work with orders, customers and products.</p>
        </BaseCard>

        <BaseCard title="API health" class="max-w-md">
            <SkeletonLoader v-if="isPending" :lines="3" />
            <ErrorState v-else-if="isError" title="Unable to reach the API" message="Check that the server is running." @retry="refetch()" />
            <dl v-else class="grid grid-cols-2 gap-1 text-sm">
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium text-green-700">{{ data.status }}</dd>
                <dt class="text-gray-500">Version</dt>
                <dd>{{ data.version }}</dd>
                <dt class="text-gray-500">Server time</dt>
                <dd>{{ data.time }}</dd>
            </dl>
        </BaseCard>
    </div>
</template>
