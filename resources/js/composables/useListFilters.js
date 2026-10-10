import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

/**
 * List filters that live in the URL query string, so a filtered list can be shared,
 * survives a refresh, and the back button works.
 *
 *   const { filters, update, search } = useListFilters({ search: '', status: '', page: 1 });
 *   filters.value       → current values (URL over defaults), passed to the API query
 *   update({ status })  → writes to the URL; any filter change resets to page 1
 *   search              → v-model for the search box, applied after 300 ms of no typing
 *
 * Picked filters, sorting and pages add a browser history entry, so Back undoes them.
 * Typing in the search box replaces the entry instead, so Back doesn't step through
 * every pause in typing.
 *
 * @param {Record<string, string|number|string[]>} defaults
 */
export function useListFilters(defaults, { debounceMs = 300 } = {}) {
    const route = useRoute();
    const router = useRouter();

    const filters = computed(() => {
        const values = { ...defaults };
        for (const [key, fallback] of Object.entries(defaults)) {
            const raw = route.query[key];
            if (raw === undefined || raw === null || raw === '') {
                continue;
            }
            if (Array.isArray(fallback)) {
                values[key] = [].concat(raw);
            } else if (typeof fallback === 'number') {
                values[key] = Number(raw) || fallback;
            } else {
                values[key] = String(raw);
            }
        }

        return values;
    });

    function update(changes, { replace = false } = {}) {
        const next = { ...filters.value, ...changes };
        if (!('page' in changes)) {
            next.page = 1;
        }

        // Keep the URL short: only values that differ from the defaults.
        const query = {};
        for (const [key, value] of Object.entries(next)) {
            const empty = value === '' || value === null || (Array.isArray(value) && value.length === 0);
            if (!empty && JSON.stringify(value) !== JSON.stringify(defaults[key])) {
                query[key] = Array.isArray(value) ? value : String(value);
            }
        }

        return replace ? router.replace({ query }) : router.push({ query });
    }

    // Search box: typed instantly, sent to the URL (and the API) after a pause.
    const search = ref(filters.value.search ?? '');
    let timer = null;
    watch(search, (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (value !== (filters.value.search ?? '')) {
                update({ search: value }, { replace: true });
            }
        }, debounceMs);
    });
    // Back button or a shared link changed the URL: show it in the box.
    watch(
        () => filters.value.search,
        (value) => {
            if (value !== search.value) {
                search.value = value ?? '';
            }
        },
    );
    onBeforeUnmount(() => clearTimeout(timer));

    return { filters, update, search };
}
