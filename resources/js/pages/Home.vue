<script setup>
import { useHealth } from '@/composables/useHealth';

const { data, isPending, isError, error, refetch } = useHealth();
</script>

<template>
    <section class="mx-auto max-w-xl p-6">
        <h1 class="mb-4 text-2xl font-semibold">Order Management System</h1>

        <div class="rounded border border-gray-200 bg-white p-4">
            <h2 class="mb-2 font-medium">API health</h2>

            <p v-if="isPending" class="text-gray-500">Checking API…</p>

            <div v-else-if="isError" class="text-red-600">
                <p>API unreachable: {{ error.message }}</p>
                <button
                    type="button"
                    class="mt-2 rounded bg-red-600 px-3 py-1 text-sm text-white"
                    @click="refetch()"
                >
                    Retry
                </button>
            </div>

            <dl v-else class="grid grid-cols-2 gap-1 text-sm">
                <dt class="text-gray-500">Status</dt>
                <dd class="font-medium text-green-700">{{ data.status }}</dd>
                <dt class="text-gray-500">Version</dt>
                <dd>{{ data.version }}</dd>
                <dt class="text-gray-500">Server time</dt>
                <dd>{{ data.time }}</dd>
            </dl>
        </div>
    </section>
</template>
