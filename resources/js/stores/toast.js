import { defineStore } from 'pinia';
import { ref } from 'vue';

/**
 * Short messages after an action ("Order created", "Could not save").
 * Any component calls toast.success(...) / toast.error(...); ToastContainer renders them.
 */
export const useToastStore = defineStore('toast', () => {
    const toasts = ref([]);
    let nextId = 1;

    function show(type, message, timeout = 4000) {
        const id = nextId++;
        toasts.value.push({ id, type, message });
        if (timeout) {
            setTimeout(() => dismiss(id), timeout);
        }

        return id;
    }

    function dismiss(id) {
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    return {
        toasts,
        show,
        dismiss,
        success: (message) => show('success', message),
        // Errors stay a little longer so they can be read.
        error: (message) => show('error', message, 7000),
        info: (message) => show('info', message),
    };
});
