<script setup>
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseDatePicker from '@/components/ui/BaseDatePicker.vue';

defineProps({
    from: { type: String, required: true },
    to: { type: String, required: true },
});

const emit = defineEmits(['change']);

const iso = (date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

function lastDays(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    emit('change', { from: iso(from), to: iso(to) });
}

function thisYear() {
    const today = new Date();
    emit('change', { from: `${today.getFullYear()}-01-01`, to: iso(today) });
}
</script>

<template>
    <div class="flex flex-wrap items-end gap-2">
        <BaseDatePicker :model-value="from" label="From" @update:model-value="emit('change', { from: $event, to })" />
        <BaseDatePicker :model-value="to" label="To" @update:model-value="emit('change', { from, to: $event })" />
        <BaseButton variant="secondary" size="sm" @click="lastDays(7)">Last 7 days</BaseButton>
        <BaseButton variant="secondary" size="sm" @click="lastDays(30)">30 days</BaseButton>
        <BaseButton variant="secondary" size="sm" @click="lastDays(90)">90 days</BaseButton>
        <BaseButton variant="secondary" size="sm" @click="thisYear">This year</BaseButton>
    </div>
</template>
