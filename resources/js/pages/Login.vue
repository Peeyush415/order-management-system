<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '', remember: false });
const errors = ref({});
const message = ref('');
const submitting = ref(false);

// Only same-app paths: "/orders/5" is fine, "//evil.com" or "https://…" is not (open redirect).
function safeRedirect(target) {
    return typeof target === 'string' && target.startsWith('/') && !target.startsWith('//') ? target : '/';
}

async function submit() {
    submitting.value = true;
    errors.value = {};
    message.value = '';

    try {
        await auth.login({ ...form });
        await router.replace(safeRedirect(route.query.redirect));
    } catch (error) {
        const response = error.response;

        if (response?.status === 422) {
            errors.value = response.data?.errors ?? {};
        } else if (response?.status === 429) {
            const seconds = response.headers?.['retry-after'] ?? 60;
            message.value = `Too many login attempts. Please try again in ${seconds} seconds.`;
        } else {
            message.value = 'Could not log in. Please try again.';
        }
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <section class="mx-auto max-w-sm px-6 pt-16 pb-6">
        <h1 class="mb-6 text-center text-2xl font-semibold">Sign in to OMS</h1>

        <form class="space-y-4 rounded border border-gray-200 bg-white p-6" novalidate @submit.prevent="submit">
            <p v-if="message" class="rounded bg-red-50 p-2 text-sm text-red-700" role="alert">{{ message }}</p>

            <div>
                <label for="email" class="mb-1 block text-sm font-medium">Email</label>
                <input
                    id="email"
                    v-model.trim="form.email"
                    type="email"
                    autocomplete="username"
                    class="w-full rounded border border-gray-300 px-3 py-2"
                    :class="{ 'border-red-500': errors.email }"
                />
                <p v-if="errors.email" class="mt-1 text-sm text-red-600">{{ errors.email[0] }}</p>
            </div>

            <div>
                <label for="password" class="mb-1 block text-sm font-medium">Password</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded border border-gray-300 px-3 py-2"
                    :class="{ 'border-red-500': errors.password }"
                />
                <p v-if="errors.password" class="mt-1 text-sm text-red-600">{{ errors.password[0] }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.remember" type="checkbox" />
                Remember me
            </label>

            <button
                type="submit"
                class="w-full rounded bg-blue-600 px-4 py-2 font-medium text-white disabled:opacity-50"
                :disabled="submitting"
            >
                {{ submitting ? 'Signing in…' : 'Sign in' }}
            </button>
        </form>

        <p class="mt-4 text-center text-xs text-gray-500">
            Demo: admin@oms.test / manager1@oms.test / staff1@oms.test, password "password"
        </p>
    </section>
</template>
