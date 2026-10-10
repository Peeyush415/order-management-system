import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { createMemoryHistory, createRouter } from 'vue-router';
import { useListFilters } from '@/composables/useListFilters';

const defaults = { search: '', status: '', priority: [], page: 1 };

async function setup(path) {
    let api;
    const Page = defineComponent({
        setup() {
            api = useListFilters(defaults);
            return () => h('div');
        },
    });
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/list', component: Page }] });
    await router.push(path);
    mount(Page, { global: { plugins: [router] } });

    return { api, router };
}

describe('useListFilters', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('reads filters from the URL over the defaults', async () => {
        const { api } = await setup('/list?status=active&priority=3&priority=4&page=2');

        expect(api.filters.value).toEqual({ search: '', status: 'active', priority: ['3', '4'], page: 2 });
    });

    it('writes changes to the URL, drops defaults and resets to page 1', async () => {
        const { api, router } = await setup('/list?page=3');

        await api.update({ status: 'inactive' });

        expect(router.currentRoute.value.query).toEqual({ status: 'inactive' });

        await api.update({ page: 2 });
        expect(router.currentRoute.value.query).toEqual({ status: 'inactive', page: '2' });
    });

    it('lets the back button undo a picked filter, but not each search pause', async () => {
        const { api, router } = await setup('/list');
        const replace = vi.spyOn(router, 'replace');
        const push = vi.spyOn(router, 'push');

        await api.update({ status: 'active' });
        expect(push).toHaveBeenCalledWith({ query: { status: 'active' } });

        api.search.value = 'ram';
        await nextTick();
        vi.advanceTimersByTime(300);
        await vi.waitFor(() => expect(replace).toHaveBeenCalledWith({ query: { status: 'active', search: 'ram' } }));
        expect(push).toHaveBeenCalledTimes(1);
    });

    it('applies the search box only after typing stops', async () => {
        const { api, router } = await setup('/list');

        api.search.value = 'ra';
        await nextTick();
        vi.advanceTimersByTime(100);
        api.search.value = 'ram';
        await nextTick();
        vi.advanceTimersByTime(299);
        expect(router.currentRoute.value.query).toEqual({});

        vi.advanceTimersByTime(1);
        await vi.waitFor(() => expect(router.currentRoute.value.query).toEqual({ search: 'ram' }));
    });

    it('fills the search box from the URL (back button, shared link)', async () => {
        const { api, router } = await setup('/list?search=priya');
        expect(api.search.value).toBe('priya');

        await router.replace({ query: { search: 'kavita' } });
        await nextTick();

        expect(api.search.value).toBe('kavita');
    });
});
