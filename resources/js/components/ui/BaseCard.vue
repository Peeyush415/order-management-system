<script setup>
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-lg border border-gray-200 bg-white">
        <header v-if="title || $slots.actions" class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
            <h2 class="font-medium text-gray-900">{{ title }}</h2>
            <slot name="actions" />
        </header>
        <div class="p-4">
            <slot />
        </div>
    </section>
</template>
