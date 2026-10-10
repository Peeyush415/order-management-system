<script setup>
import { computed } from 'vue';
import BaseDatePicker from '@/components/ui/BaseDatePicker.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import { useLookups } from '@/composables/useLookups';
import { useOrderForm } from '@/composables/useOrderForm';

const { mode, form, errors } = useOrderForm();
const { data: lookups } = useLookups();

const priorities = computed(() => lookups.value?.order_priorities ?? []);
const assignees = computed(() => [{ value: '', label: 'Unassigned' }, ...(lookups.value?.assignable_users ?? []).map((u) => ({ value: String(u.id), label: u.name }))]);
const error = (field) => errors.value[field]?.[0] ?? '';
</script>

<template>
    <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
        <BaseSelect v-model.number="form.priority" label="Priority" :options="priorities" :error="error('priority')" />
        <BaseDatePicker v-model="form.order_date" label="Order date" :error="error('order_date')" />

        <template v-if="mode === 'create'">
            <BaseSelect v-model="form.assigned_to" label="Assign to" :options="assignees" :error="error('assigned_to')" />
            <fieldset>
                <legend class="mb-1 text-sm font-medium text-gray-700">Save as</legend>
                <label class="mr-4 inline-flex items-center gap-2 text-sm"><input v-model="form.status" type="radio" value="pending" /> Pending (submit)</label>
                <label class="inline-flex items-center gap-2 text-sm"><input v-model="form.status" type="radio" value="draft" /> Draft</label>
                <p v-if="error('status')" class="mt-1 text-sm text-red-600">{{ error('status') }}</p>
            </fieldset>
            <div class="sm:col-span-2">
                <label for="order-note" class="mb-1 block text-sm font-medium text-gray-700">Note (optional)</label>
                <textarea
                    id="order-note"
                    v-model="form.note"
                    rows="3"
                    class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                />
                <p v-if="error('note')" class="mt-1 text-sm text-red-600">{{ error('note') }}</p>
            </div>
        </template>
        <p v-else class="text-sm text-gray-500 sm:col-span-2">
            To change the assignee or status, or to add a note, use the actions on the order page.
        </p>
    </div>
</template>
