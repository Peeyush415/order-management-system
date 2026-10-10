import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h } from 'vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { isActiveLink, navigation, visibleNavigation } from '@/config/navigation';
import { createCan } from '@/directives/can';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';

describe('sidebar navigation', () => {
    const existing = new Set(['home', 'orders.index', 'customers.index', 'products.index', 'users.index']);
    const hasRoute = (name) => existing.has(name);

    it('shows only links the user may use and whose page exists', () => {
        const staffPermissions = ['orders.view_assigned', 'customers.view', 'products.view', 'notes.create'];
        const labels = visibleNavigation(navigation, { can: (p) => staffPermissions.includes(p), hasRoute }).map((l) => l.label);

        // No Users (no permission), no Audit log (page not built yet).
        expect(labels).toEqual(['Dashboard', 'Orders', 'Customers', 'Products']);
    });

    it('keeps a section highlighted on its sub-pages', () => {
        expect(isActiveLink('/customers', '/customers/90')).toBe(true);
        expect(isActiveLink('/products', '/products/5/edit')).toBe(true);
        expect(isActiveLink('/customers', '/customers-archive')).toBe(false);
        expect(isActiveLink('/', '/customers')).toBe(false);
        expect(isActiveLink('/', '/')).toBe(true);
    });

    it('shows admin links to an admin', () => {
        const labels = visibleNavigation(navigation, { can: () => true, hasRoute }).map((l) => l.label);

        expect(labels).toContain('Users');
    });
});

describe('v-can directive', () => {
    let can;
    beforeEach(() => {
        const pinia = createPinia();
        setActivePinia(pinia);
        can = createCan(pinia);
    });

    const Button = defineComponent({
        props: { permission: { type: [String, Array], required: true } },
        render() {
            return h('div', [h('button', { id: 'target' }, 'Delete')]);
        },
        mounted() {
            can.mounted(this.$el.querySelector('#target'), { value: this.permission });
        },
    });

    it('hides the element without the permission and shows it with one of several', () => {
        useAuthStore().user = { id: 4, roles: ['staff'], permissions: ['notes.create'] };

        const hidden = mount(Button, { props: { permission: 'orders.delete' } });
        const shown = mount(Button, { props: { permission: ['orders.delete', 'notes.create'] } });

        expect(hidden.find('#target').element.style.display).toBe('none');
        expect(hidden.find('#target').attributes('aria-hidden')).toBe('true');
        expect(shown.find('#target').element.style.display).toBe('');
    });
});

describe('toast store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.useFakeTimers();
    });
    afterEach(() => vi.useRealTimers());

    it('shows a message and removes it after a while', () => {
        const toast = useToastStore();

        toast.success('Order created');
        toast.error('Could not save');
        expect(toast.toasts.map((t) => t.type)).toEqual(['success', 'error']);

        vi.advanceTimersByTime(4000);
        expect(toast.toasts.map((t) => t.message)).toEqual(['Could not save']);

        vi.advanceTimersByTime(3000);
        expect(toast.toasts).toEqual([]);
    });

    it('can be dismissed by hand', () => {
        const toast = useToastStore();
        const id = toast.info('Saved');

        toast.dismiss(id);

        expect(toast.toasts).toEqual([]);
    });
});
