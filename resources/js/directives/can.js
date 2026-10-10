import { useAuthStore } from '@/stores/auth';

/**
 * v-can="'orders.delete'" or v-can="['orders.update', 'orders.assign']" (any of them).
 * Hides the element when the user lacks the permission. A UI convenience only:
 * the API checks every request, so a hidden button is never the only protection.
 *
 * Created with the app's Pinia instance, so it always reads that app's logged-in user.
 *
 * @param {import('pinia').Pinia} pinia
 */
export function createCan(pinia) {
    function apply(el, binding) {
        const auth = useAuthStore(pinia);
        const allowed = [].concat(binding.value).some((permission) => auth.can(permission));

        el.style.display = allowed ? '' : 'none';
        if (allowed) {
            el.removeAttribute('aria-hidden');
        } else {
            el.setAttribute('aria-hidden', 'true');
        }
    }

    return { mounted: apply, updated: apply };
}
