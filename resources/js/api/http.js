import axios from 'axios';

/**
 * Single axios instance for the whole SPA.
 * Sanctum SPA auth runs on the session cookie, so every request must send
 * cookies and echo the XSRF-TOKEN cookie back as the X-XSRF-TOKEN header.
 */
const http = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

/**
 * Runs `handler` whenever the API answers 401 (session expired or account deactivated).
 * Registered from main.js so this module doesn't import the router or the store.
 * Requests can opt out with `{ skipAuthRedirect: true }`.
 */
export function onUnauthorized(handler) {
    http.interceptors.response.use(
        (response) => response,
        (error) => {
            if (error.response?.status === 401 && !error.config?.skipAuthRedirect) {
                handler(error);
            }

            return Promise.reject(error);
        },
    );
}

export default http;
