<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseDatePicker from '@/components/ui/BaseDatePicker.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { AUDIT_EVENTS, AUDIT_SUBJECTS, subjectLink, useAuditLogs } from '@/composables/useAuditLogs';
import { useListFilters } from '@/composables/useListFilters';
import { useUsers } from '@/composables/useUsers';
import { useAuthStore } from '@/stores/auth';
import { formatDateTime } from '@/utils/date';

const router = useRouter();
const auth = useAuthStore();

const defaults = { user_id: '', event: '', auditable_type: '', from: '', to: '', page: 1 };
const { filters, update } = useListFilters(defaults);
const { data, isPending, isError, refetch } = useAuditLogs(filters);

// The "who" filter needs the user list, which only users.manage may read.
const canListUsers = computed(() => auth.can('users.manage'));
const { data: users } = useUsers(computed(() => ({ per_page: 100, sort: 'name' })), { enabled: canListUsers });
const userOptions = computed(() => (users.value?.data ?? []).map((user) => ({ value: String(user.id), label: user.name })));

const hasFilters = computed(() => Object.keys(defaults).some((key) => key !== 'page' && filters.value[key]));
const eventColors = { created: 'green', deleted: 'red', status_changed: 'blue', assignment_changed: 'purple', permissions_changed: 'orange', role_changed: 'orange' };

/** "Normal → High" for a change; just the value when the record was created or deleted. */
function describe(change) {
    return change.old !== null && change.new !== null ? `${change.old} → ${change.new}` : (change.new ?? change.old ?? '—');
}

function open(subject) {
    const link = subjectLink(subject);
    if (link) {
        router.push(link);
    }
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-3">
            <BaseSelect
                v-if="canListUsers"
                :model-value="filters.user_id"
                :options="userOptions"
                placeholder="All users"
                label="User"
                class="w-44"
                @update:model-value="update({ user_id: $event })"
            />
            <BaseSelect :model-value="filters.event" :options="AUDIT_EVENTS" placeholder="All actions" label="Action" class="w-44" @update:model-value="update({ event: $event })" />
            <BaseSelect
                :model-value="filters.auditable_type"
                :options="AUDIT_SUBJECTS"
                placeholder="All records"
                label="Record"
                class="w-36"
                @update:model-value="update({ auditable_type: $event })"
            />
            <BaseDatePicker :model-value="filters.from" label="From" class="w-40" @update:model-value="update({ from: $event })" />
            <BaseDatePicker :model-value="filters.to" label="To" class="w-40" @update:model-value="update({ to: $event })" />
            <BaseButton v-if="hasFilters" variant="ghost" size="sm" @click="update({ ...defaults })">Clear filters</BaseButton>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white">
            <div v-if="isPending" class="p-4"><SkeletonLoader :lines="8" /></div>
            <ErrorState v-else-if="isError" title="Unable to load the audit log." @retry="refetch()" />
            <EmptyState v-else-if="!data.data.length" title="No audit entries found." message="Try changing the filters." />
            <template v-else>
                <ul class="divide-y divide-gray-100">
                    <li v-for="log in data.data" :key="log.id" class="px-4 py-3 text-sm" data-test="audit-row">
                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <span class="font-medium text-gray-900">{{ log.user?.name ?? 'System' }}</span>
                            <BaseBadge :color="eventColors[log.event] ?? 'gray'">{{ log.event_label }}</BaseBadge>
                            <span class="text-gray-500">{{ log.subject.type_label }}</span>
                            <button
                                v-if="subjectLink(log.subject)"
                                type="button"
                                class="font-medium text-blue-600 hover:underline"
                                @click="open(log.subject)"
                            >
                                {{ log.subject.label }}
                            </button>
                            <span v-else class="font-medium">{{ log.subject.label }}</span>
                            <span class="ml-auto text-xs text-gray-400">{{ formatDateTime(log.created_at) }}</span>
                        </div>
                        <ul v-if="log.changes.length" class="mt-1.5 space-y-0.5 text-gray-600">
                            <li v-for="change in log.changes" :key="change.field">
                                <span class="text-gray-500">{{ change.label }}:</span> {{ describe(change) }}
                            </li>
                        </ul>
                    </li>
                </ul>
                <Pagination v-if="data.meta.total" :meta="data.meta" @change="update({ page: $event })" />
            </template>
        </div>
    </div>
</template>
