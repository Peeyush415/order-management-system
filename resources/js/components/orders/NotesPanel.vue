<script setup>
import { computed, ref, toRef } from 'vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useOrderMutations, useOrderNotes } from '@/composables/useOrder';
import { useToastStore } from '@/stores/toast';
import { formatDateTime } from '@/utils/date';

const props = defineProps({
    orderId: { type: String, required: true },
    /** Whether this user may add notes to this order (decided by the page). */
    canAdd: { type: Boolean, default: false },
});

const id = toRef(props, 'orderId');
const toast = useToastStore();
const { data, isPending, isError, refetch, hasNextPage, fetchNextPage, isFetchingNextPage } = useOrderNotes(id);
const { addNote } = useOrderMutations(id);
const { error, message, clear, fromResponse } = useFormErrors();

const notes = computed(() => data.value?.pages.flatMap((page) => page.data) ?? []);
const body = ref('');

async function submit() {
    clear();
    try {
        await addNote.mutateAsync(body.value.trim());
        body.value = '';
        toast.success('Note added');
    } catch (e) {
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}
</script>

<template>
    <BaseCard title="Notes">
        <form v-if="canAdd" class="mb-4 space-y-2" novalidate @submit.prevent="submit">
            <label for="new-note" class="sr-only">New note</label>
            <textarea
                id="new-note"
                v-model="body"
                rows="3"
                placeholder="Add a note for your team…"
                class="w-full rounded border px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                :class="error('body') ? 'border-red-500' : 'border-gray-300'"
            />
            <p v-if="error('body')" class="text-sm text-red-600">{{ error('body') }}</p>
            <div class="flex justify-end">
                <BaseButton type="submit" size="sm" :loading="addNote.isPending.value">Add note</BaseButton>
            </div>
        </form>

        <SkeletonLoader v-if="isPending" :lines="3" />
        <ErrorState v-else-if="isError" title="Unable to load notes." @retry="refetch()" />
        <EmptyState v-else-if="!notes.length" title="No notes yet." />
        <template v-else>
            <ul class="space-y-3">
                <!-- Plain text interpolation: note bodies are user input and are never rendered as HTML. -->
                <li v-for="note in notes" :key="note.id" class="rounded bg-gray-50 p-3 text-sm">
                    <p class="whitespace-pre-line text-gray-900">{{ note.body }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ note.user?.name }} · {{ formatDateTime(note.created_at) }}</p>
                </li>
            </ul>
            <BaseButton v-if="hasNextPage" variant="ghost" size="sm" class="mt-2" :loading="isFetchingNextPage" @click="fetchNextPage()">
                Load more
            </BaseButton>
        </template>
    </BaseCard>
</template>
