import { computed, inject, provide, reactive, ref } from 'vue';
import { useOrderPricing } from '@/composables/useOrderPricing';
import { centsToInput, toCents } from '@/utils/money';

/*
 * State of the order wizard, shared by its steps through provide/inject.
 * Only what the user chooses is sent; prices and totals are never part of the payload.
 */

const KEY = Symbol('order-form');

export const STEPS = ['Customer', 'Products', 'Pricing', 'Assignment & priority', 'Review & submit'];

function today() {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function emptyForm() {
    return {
        customer_id: '',
        // Each line keeps a snapshot of the product for display and the price preview.
        items: [],
        discount_type: '',
        discount_input: '',
        priority: 2,
        order_date: today(),
        assigned_to: '',
        status: 'pending',
        note: '',
        version: null,
    };
}

/** Which step shows the error for an API field (e.g. "items.0.quantity" → Products). */
export function stepForField(field) {
    if (field.startsWith('customer')) return 0;
    if (field.startsWith('items')) return 1;
    if (field.startsWith('discount')) return 2;
    return 3;
}

export function provideOrderForm({ mode }) {
    const form = reactive(emptyForm());
    const errors = ref({});

    const discountValue = computed(() => {
        if (form.discount_type === 'fixed') return toCents(form.discount_input) ?? 0;
        if (form.discount_type === 'percent') return Math.round(Number(form.discount_input || 0) * 100);
        return 0;
    });
    const pricing = useOrderPricing(() => form.items, () => form.discount_type, discountValue);

    function addProduct(product) {
        if (!form.items.some((line) => line.product_id === product.id)) {
            form.items.push({
                product_id: product.id,
                name: product.name,
                sku: product.sku,
                unit_price: product.price,
                tax_rate_bps: product.tax_rate_bps,
                stock_quantity: product.stock_quantity,
                quantity: 1,
            });
        }
    }

    function removeLine(index) {
        form.items.splice(index, 1);
    }

    /** Fill the form from an order loaded for editing. */
    function fill(order) {
        Object.assign(form, emptyForm(), {
            customer_id: order.customer.id,
            items: order.items.map((item) => ({
                product_id: item.product_id,
                name: item.product_name,
                sku: item.sku,
                unit_price: item.unit_price,
                tax_rate_bps: item.tax_rate_bps,
                stock_quantity: null,
                quantity: item.quantity,
            })),
            discount_type: order.discount_type ?? '',
            discount_input:
                order.discount_type === 'fixed' ? centsToInput(order.discount_value) : order.discount_type === 'percent' ? String(order.discount_value / 100) : '',
            priority: order.priority.value,
            order_date: order.order_date,
            version: order.version,
        });
        errors.value = {};
    }

    /** Front-end checks for one step; the API repeats all of them on submit. */
    function validateStep(step) {
        const found = {};
        if (step === 0 && !form.customer_id) {
            found.customer_id = ['Customer is required.'];
        }
        if (step === 1) {
            if (!form.items.length) {
                found.items = ['Order must contain at least one product.'];
            }
            form.items.forEach((line, i) => {
                if (!Number.isInteger(Number(line.quantity)) || Number(line.quantity) < 1) {
                    found[`items.${i}.quantity`] = ['Quantity must be greater than 0.'];
                }
            });
        }
        if (step === 2 && form.discount_type) {
            const value = Number(form.discount_input);
            if (form.discount_type === 'fixed' && toCents(form.discount_input) === null) {
                found.discount_value = ['Enter the discount as an amount, e.g. 20 or 20.50.'];
            } else if (form.discount_type === 'percent' && (!(value >= 0) || value > 100 || form.discount_input === '')) {
                found.discount_value = ['Enter a percentage between 0 and 100.'];
            }
        }
        if (step === 3 && !form.order_date) {
            found.order_date = ['Order date is required.'];
        }
        errors.value = found;

        return Object.keys(found).length === 0;
    }

    function toPayload() {
        const payload = {
            customer_id: Number(form.customer_id),
            items: form.items.map((line) => ({ product_id: line.product_id, quantity: Number(line.quantity) })),
            discount_type: form.discount_type || null,
            discount_value: form.discount_type ? discountValue.value : null,
            priority: Number(form.priority),
            order_date: form.order_date,
        };
        if (mode === 'create') {
            payload.assigned_to = form.assigned_to ? Number(form.assigned_to) : null;
            payload.status = form.status;
            payload.note = form.note.trim() || null;
        } else {
            payload.version = form.version;
        }

        return payload;
    }

    const context = { mode, form, errors, pricing, addProduct, removeLine, fill, validateStep, toPayload };
    provide(KEY, context);

    return context;
}

export function useOrderForm() {
    return inject(KEY);
}
