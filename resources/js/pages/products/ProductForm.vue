<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import StatusBadge from '@/components/StatusBadge.vue';
import StockAdjustModal from '@/components/products/StockAdjustModal.vue';
import BaseBadge from '@/components/ui/BaseBadge.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseSelect from '@/components/ui/BaseSelect.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import SkeletonLoader from '@/components/ui/SkeletonLoader.vue';
import { useFormErrors } from '@/composables/useFormErrors';
import { useProduct, useProductMutations } from '@/composables/useProducts';
import { useToastStore } from '@/stores/toast';
import { centsToInput, toCents } from '@/utils/money';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

/** Same page for create (/products/new) and edit (/products/:id/edit). */
const id = computed(() => route.params.id ?? '');
const isEdit = computed(() => !!id.value);

// Price is typed in dollars ("125.50") and sent as integer cents; tax is typed in percent.
const form = reactive({ sku: '', name: '', description: '', price: '', tax_percent: '10', low_stock_threshold: '10', initial_stock: '0' });

const { data: product, isPending: loading, isError: loadFailed, refetch } = useProduct(id);
watch(
    product,
    (value) => {
        if (value) {
            Object.assign(form, {
                sku: value.sku,
                name: value.name,
                description: value.description ?? '',
                price: centsToInput(value.price),
                tax_percent: String(value.tax_rate_bps / 100),
                low_stock_threshold: String(value.low_stock_threshold),
            });
        }
    },
    { immediate: true },
);

const { create, update, changeStatus } = useProductMutations();
const { error, errors, message, clear, fromResponse } = useFormErrors();
const saving = computed(() => create.isPending.value || update.isPending.value);

function payload() {
    const data = {
        sku: form.sku,
        name: form.name,
        description: form.description || null,
        // An unparseable price is sent as-is so the API returns a field error for it.
        price: toCents(form.price) ?? form.price,
        tax_rate_bps: Math.round(Number(form.tax_percent) * 100),
        low_stock_threshold: Number(form.low_stock_threshold),
    };
    if (!isEdit.value) {
        data.initial_stock = Number(form.initial_stock);
    }

    return data;
}

async function submit() {
    clear();
    try {
        const saved = isEdit.value
            ? await update.mutateAsync({ id: id.value, payload: payload() })
            : await create.mutateAsync(payload());

        toast.success(isEdit.value ? 'Product updated' : 'Product created');
        if (!isEdit.value) {
            router.push({ name: 'products.edit', params: { id: saved.id } });
        }
    } catch (e) {
        if (!fromResponse(e)) {
            toast.error(message.value);
        }
    }
}

const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'discontinued', label: 'Discontinued' },
];
const newStatus = ref('');
watch(product, (value) => (newStatus.value = value?.status.value ?? ''), { immediate: true });

async function saveStatus() {
    try {
        await changeStatus.mutateAsync({ id: product.value.id, status: newStatus.value });
        toast.success('Status updated');
    } catch (e) {
        toast.error(e.response?.data?.message ?? 'Could not change the status.');
    }
}

const adjusting = ref(false);
</script>

<template>
    <div class="max-w-3xl space-y-4">
        <SkeletonLoader v-if="isEdit && loading" :lines="6" />
        <ErrorState v-else-if="isEdit && loadFailed" title="Unable to load this product." @retry="refetch()" />

        <template v-else>
            <form class="space-y-4" novalidate @submit.prevent="submit">
                <BaseCard :title="isEdit ? 'Edit product' : 'New product'">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <BaseInput v-model.trim="form.sku" label="SKU" hint="Letters, numbers and dashes; saved in capitals." :error="error('sku')" />
                        <BaseInput v-model.trim="form.name" label="Name" :error="error('name')" />
                        <BaseInput v-model.trim="form.price" label="Price ($)" inputmode="decimal" placeholder="125.50" :error="error('price')" />
                        <BaseInput v-model="form.tax_percent" label="Tax (%)" type="number" step="0.01" min="0" max="100" :error="error('tax_rate_bps')" />
                        <BaseInput v-model="form.low_stock_threshold" label="Low-stock threshold" type="number" min="0" :error="error('low_stock_threshold')" />
                        <BaseInput
                            v-if="!isEdit"
                            v-model="form.initial_stock"
                            label="Initial stock"
                            type="number"
                            min="0"
                            hint="Recorded as the opening entry in the stock ledger."
                            :error="error('initial_stock')"
                        />
                        <div class="sm:col-span-2">
                            <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Description</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none"
                            />
                            <p v-if="errors.description" class="mt-1 text-sm text-red-600">{{ error('description') }}</p>
                        </div>
                    </div>
                </BaseCard>
                <div class="flex justify-end gap-2">
                    <BaseButton variant="secondary" :disabled="saving" @click="router.push({ name: 'products.index' })">Back to products</BaseButton>
                    <BaseButton type="submit" :loading="saving">{{ isEdit ? 'Save changes' : 'Create product' }}</BaseButton>
                </div>
            </form>

            <template v-if="isEdit && product">
                <BaseCard title="Stock">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm">
                            <span class="text-2xl font-semibold">{{ product.stock_quantity }}</span> in stock
                            <BaseBadge v-if="product.is_low_stock" color="orange" class="ml-2">Low stock</BaseBadge>
                        </p>
                        <BaseButton variant="secondary" @click="adjusting = true">Adjust stock</BaseButton>
                    </div>
                </BaseCard>

                <BaseCard title="Status">
                    <div class="flex flex-wrap items-end gap-3">
                        <StatusBadge :status="product.status" />
                        <BaseSelect v-model="newStatus" :options="statusOptions" aria-label="New status" class="w-44" />
                        <BaseButton variant="secondary" :disabled="newStatus === product.status.value" :loading="changeStatus.isPending.value" @click="saveStatus">
                            Change status
                        </BaseButton>
                    </div>
                </BaseCard>

                <StockAdjustModal :open="adjusting" :product="product" @close="adjusting = false" />
            </template>
        </template>
    </div>
</template>
