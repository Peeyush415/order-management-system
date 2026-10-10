<script setup>
import { computed, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ActivityFeed from '@/components/orders/ActivityFeed.vue';
import AssignModal from '@/components/orders/AssignModal.vue';
import NotesPanel from '@/components/orders/NotesPanel.vue';
import OrderItemsTable from '@/components/orders/OrderItemsTable.vue';
import OrderSummary from '@/components/orders/OrderSummary.vue';
import OrderTimeline from '@/components/orders/OrderTimeline.vue';
import StatusChangeModal from '@/components/orders/StatusChangeModal.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useOrder, useOrderMutations, useOrderTransitions, useStatusHistory } from '@/composables/useOrder';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const id = computed(() => String(route.params.id));
const { data: order, isPending, isError, error, refetch } = useOrder(id);
const loaded = () => !!order.value;

const { data: transitionData } = useOrderTransitions(id, { enabled: loaded });
const { data: history, isPending: historyLoading, isError: historyFailed, refetch: refetchHistory } = useStatusHistory(id, { enabled: loaded });
const { remove } = useOrderMutations(id);

// What went wrong when loading: the API answers 403 for someone else's order and 404 for a missing one.
const loadError = computed(() => {
    const status = error.value?.response?.status;
    if (status === 403) {
        return { title: "You don't have access to this order.", message: 'Staff can only open orders assigned to them.' };
    }
    if (status === 404) {
        return { title: 'Order not found.', message: 'It may have been deleted.' };
    }
    return { title: 'Unable to load this order.', message: 'Please try again.' };
});

// Buttons follow the permissions and the order's state; the API checks both again.
const transitions = computed(() => transitionData.value?.transitions ?? []);
const status = computed(() => order.value?.status.value);
const canChangeStatus = computed(() => transitions.value.length > 0);
const canCancel = computed(() => transitions.value.some((t) => t.value === 'cancelled'));
const canAssign = computed(() => auth.can('orders.assign') && !['completed', 'cancelled'].includes(status.value));
const canEdit = computed(() => router.hasRoute('orders.edit') && auth.can('orders.update') && ['draft', 'pending'].includes(status.value));
const canDelete = computed(() => auth.can('orders.delete') && ['draft', 'pending', 'cancelled'].includes(status.value));
const canAddNote = computed(() => auth.can('notes.create'));

const statusModal = ref({ open: false, preset: '' });
const assigning = ref(false);
const confirmingDelete = ref(false);

function openStatus(preset = '') {
    statusModal.value = { open: true, preset };
}

async function destroy() {
    try {
        await remove.mutateAsync();
        toast.success(`Order ${order.value.order_number} deleted`);
        router.push({ name: 'orders.index' });
    } catch (e) {
        toast.error(e.response?.data?.message ?? 'Could not delete the order.');
    } finally {
        confirmingDelete.value = false;
    }
}
</script>

<template>
    <SkeletonLoader v-if="isPending" :lines="10" />
    <div v-else-if="isError" class="rounded-lg border border-gray-200 bg-white">
        <ErrorState :title="loadError.title" :message="loadError.message" @retry="refetch()" />
    </div>

    <div v-else class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <RouterLink :to="{ name: 'orders.index' }" class="text-sm text-blue-600 hover:underline">← Orders</RouterLink>
                <h2 class="text-xl font-semibold text-gray-900">{{ order.order_number }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <BaseButton v-if="canChangeStatus" size="sm" @click="openStatus()">Change status</BaseButton>
                <BaseButton v-if="canAssign" variant="secondary" size="sm" @click="assigning = true">Assign</BaseButton>
                <BaseButton v-if="canEdit" variant="secondary" size="sm" @click="router.push({ name: 'orders.edit', params: { id: order.id } })">Edit</BaseButton>
                <BaseButton v-if="canCancel" variant="secondary" size="sm" @click="openStatus('cancelled')">Cancel order</BaseButton>
                <BaseButton v-if="canDelete" variant="danger" size="sm" @click="confirmingDelete = true">Delete</BaseButton>
            </div>
        </div>

        <OrderSummary :order="order" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <OrderItemsTable :order="order" />
                <NotesPanel :order-id="id" :can-add="canAddNote" />
            </div>
            <div class="space-y-6">
                <BaseCard title="Customer">
                    <dl class="space-y-2 text-sm">
                        <div>
                            <dt class="text-gray-500">Name</dt>
                            <dd>
                                <RouterLink :to="{ name: 'customers.show', params: { id: order.customer.id } }" class="text-blue-600 hover:underline">
                                    {{ order.customer.name }}
                                </RouterLink>
                            </dd>
                        </div>
                        <div><dt class="text-gray-500">Email</dt><dd>{{ order.customer.email }}</dd></div>
                        <div><dt class="text-gray-500">Phone</dt><dd>{{ order.customer.phone ?? '—' }}</dd></div>
                        <div>
                            <dt class="text-gray-500">Address</dt>
                            <dd>
                                {{ order.customer.address.line1 }}<template v-if="order.customer.address.line2">, {{ order.customer.address.line2 }}</template><br />
                                {{ [order.customer.address.city, order.customer.address.state, order.customer.address.postal_code].filter(Boolean).join(', ') }}<br />
                                {{ order.customer.address.country }}
                            </dd>
                        </div>
                    </dl>
                </BaseCard>
                <OrderTimeline :history="history ?? []" :loading="historyLoading" :error="historyFailed" @retry="refetchHistory()" />
                <ActivityFeed :order-id="id" />
            </div>
        </div>

        <StatusChangeModal
            :open="statusModal.open"
            :order-id="id"
            :transitions="transitions"
            :preset="statusModal.preset"
            @close="statusModal.open = false"
        />
        <AssignModal :open="assigning" :order-id="id" :current="order.assignee?.id ?? null" @close="assigning = false" />
        <ConfirmDialog
            :open="confirmingDelete"
            :title="`Delete ${order.order_number}?`"
            message="The order will be removed from the list. Only draft, pending or cancelled orders can be deleted."
            confirm-label="Delete"
            danger
            :loading="remove.isPending.value"
            @confirm="destroy"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>
