import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BaseButton from '@/components/ui/BaseButton.vue';
import BaseInput from '@/components/ui/BaseInput.vue';
import BaseModal from '@/components/ui/BaseModal.vue';
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue';
import DataTable from '@/components/ui/DataTable.vue';
import ErrorState from '@/components/ui/ErrorState.vue';
import Pagination from '@/components/ui/Pagination.vue';

// Render teleported content (modals) in place so tests can find it.
const global = { stubs: { teleport: true } };

describe('BaseButton', () => {
    it('is disabled and busy while loading', () => {
        const wrapper = mount(BaseButton, { props: { loading: true }, slots: { default: 'Save' } });

        expect(wrapper.attributes('disabled')).toBeDefined();
        expect(wrapper.attributes('aria-busy')).toBe('true');
        expect(wrapper.text()).toBe('Save');
    });
});

describe('BaseInput', () => {
    it('binds v-model and shows the error under the field', async () => {
        const wrapper = mount(BaseInput, {
            props: { label: 'Email', modelValue: '', error: 'The email field is required.', 'onUpdate:modelValue': (v) => wrapper.setProps({ modelValue: v }) },
        });

        await wrapper.find('input').setValue('a@b.test');

        expect(wrapper.props('modelValue')).toBe('a@b.test');
        expect(wrapper.find('label').attributes('for')).toBe(wrapper.find('input').attributes('id'));
        expect(wrapper.text()).toContain('The email field is required.');
        expect(wrapper.find('input').attributes('aria-invalid')).toBe('true');
    });
});

describe('Pagination', () => {
    const meta = { current_page: 3, per_page: 15, total: 100, last_page: 7 };

    it('shows the range and emits the chosen page', async () => {
        const wrapper = mount(Pagination, { props: { meta } });

        expect(wrapper.text()).toContain('Showing 31–45 of 100');
        await wrapper.findAll('button').find((b) => b.text() === 'Next').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === '5').trigger('click');

        expect(wrapper.emitted('change')).toEqual([[4], [5]]);
    });

    it('does not go before the first page', async () => {
        const wrapper = mount(Pagination, { props: { meta: { ...meta, current_page: 1 } } });

        const previous = wrapper.findAll('button').find((b) => b.text() === 'Previous');
        expect(previous.attributes('disabled')).toBeDefined();
    });
});

describe('DataTable', () => {
    const columns = [
        { key: 'order_number', label: 'Order' },
        { key: 'total', label: 'Total' },
    ];

    it('renders rows and lets a slot format a cell', () => {
        const wrapper = mount(DataTable, {
            props: { columns, rows: [{ id: 1, order_number: 'ORD-10001', total: 36300 }] },
            slots: { 'cell-total': '<template #cell-total="{ value }">${{ value / 100 }}</template>' },
        });

        expect(wrapper.text()).toContain('ORD-10001');
        expect(wrapper.text()).toContain('$363');
    });

    it('shows skeleton rows while loading and an empty state when there is nothing', async () => {
        const wrapper = mount(DataTable, { props: { columns, rows: [], loading: true } });
        expect(wrapper.findAll('[data-test="skeleton-row"]')).toHaveLength(5);

        await wrapper.setProps({ loading: false, emptyTitle: 'No orders found.', emptyMessage: 'Try changing your filters.' });
        expect(wrapper.text()).toContain('No orders found.');
        expect(wrapper.text()).toContain('Try changing your filters.');
    });

    it('emits row-click when clickable', async () => {
        const row = { id: 7, order_number: 'ORD-10007', total: 1 };
        const wrapper = mount(DataTable, { props: { columns, rows: [row], clickable: true } });

        await wrapper.find('tbody tr').trigger('click');

        expect(wrapper.emitted('row-click')[0]).toEqual([row]);
    });
});

describe('ErrorState', () => {
    it('emits retry', async () => {
        const wrapper = mount(ErrorState, { props: { title: 'Unable to load orders.' } });

        await wrapper.find('button').trigger('click');

        expect(wrapper.text()).toContain('Unable to load orders.');
        expect(wrapper.emitted('retry')).toHaveLength(1);
    });
});

describe('BaseModal and ConfirmDialog', () => {
    it('closes on Escape and on the backdrop', async () => {
        const wrapper = mount(BaseModal, { props: { open: true, title: 'Edit' }, global });

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.find('[data-test="backdrop"]').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(2);
        wrapper.unmount();
    });

    it('renders nothing when closed', () => {
        const wrapper = mount(BaseModal, { props: { open: false, title: 'Edit' }, global });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('asks before acting and reports the answer', async () => {
        const wrapper = mount(ConfirmDialog, {
            props: { open: true, title: 'Delete order?', message: 'This cannot be undone.', confirmLabel: 'Delete', danger: true },
            global,
        });

        expect(wrapper.text()).toContain('This cannot be undone.');
        await wrapper.find('[data-test="confirm"]').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Cancel').trigger('click');

        expect(wrapper.emitted('confirm')).toHaveLength(1);
        expect(wrapper.emitted('cancel')).toHaveLength(1);
        wrapper.unmount();
    });
});
