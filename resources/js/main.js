import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import App from './App.vue';
import router from './router';
import { onUnauthorized } from './api/http';
import { can } from './directives/can';
import { useAuthStore } from './stores/auth';

const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            retry: 1,
            refetchOnWindowFocus: false,
        },
    },
});

const pinia = createPinia();
const app = createApp(App).use(pinia).use(router).use(VueQueryPlugin, { queryClient }).directive('can', can);

// Session expired or account deactivated: forget the user and go to login, then come back.
onUnauthorized(() => {
    useAuthStore(pinia).reset();
    queryClient.clear();

    const current = router.currentRoute.value;
    if (current.meta.requiresAuth) {
        router.push({ name: 'login', query: { redirect: current.fullPath } });
    }
});

app.mount('#app');
