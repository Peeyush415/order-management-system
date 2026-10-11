import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusBadge from '@/components/StatusBadge.vue';
import PriorityBadge from '@/components/PriorityBadge.vue';

describe('StatusBadge', () => {
    it.each([
        ['pending', 'Pending', 'bg-yellow-100'],
        ['confirmed', 'Confirmed', 'bg-blue-100'],
        ['completed', 'Completed', 'bg-green-100'],
        ['cancelled', 'Cancelled', 'bg-red-100'],
        ['inactive', 'Inactive', 'bg-gray-100'],
    ])('%s shows its label in its colour', (value, label, colour) => {
        const badge = mount(StatusBadge, { props: { status: { value, label } } }).get('span');

        expect(badge.text()).toBe(label);
        expect(badge.classes()).toContain(colour);
    });

    it('falls back to grey for an unknown status', () => {
        const badge = mount(StatusBadge, { props: { status: { value: 'archived', label: 'Archived' } } }).get('span');

        expect(badge.classes()).toContain('bg-gray-100');
    });
});

describe('PriorityBadge', () => {
    it.each([
        [1, 'Low', 'bg-gray-100'],
        [2, 'Normal', 'bg-blue-100'],
        [3, 'High', 'bg-orange-100'],
        [4, 'Urgent', 'bg-red-100'],
    ])('priority %i is %s', (value, label, colour) => {
        const badge = mount(PriorityBadge, { props: { priority: { value, label } } }).get('span');

        expect(badge.text()).toBe(label);
        expect(badge.classes()).toContain(colour);
    });
});
