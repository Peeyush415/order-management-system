import { ref } from 'vue';

/**
 * Maps a 422 response onto form fields: { email: ['Taken'] } → error(email) = 'Taken'.
 * Other errors give a general message instead.
 */
export function useFormErrors() {
    const errors = ref({});
    const message = ref('');

    function clear() {
        errors.value = {};
        message.value = '';
    }

    /** @returns {boolean} true when the error was a validation error mapped to fields */
    function fromResponse(error) {
        const response = error?.response;
        errors.value = response?.status === 422 ? (response.data?.errors ?? {}) : {};
        message.value = response?.data?.message ?? 'Something went wrong. Please try again.';

        return response?.status === 422 && Object.keys(errors.value).length > 0;
    }

    function error(field) {
        return errors.value[field]?.[0] ?? '';
    }

    return { errors, message, clear, fromResponse, error };
}
