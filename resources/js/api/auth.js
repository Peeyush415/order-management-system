import http from './http';

/** Sanctum sets the XSRF-TOKEN cookie; axios sends it back as X-XSRF-TOKEN on writes. */
export function getCsrfCookie() {
    // Outside /api/v1, so override the base URL.
    return http.get('/sanctum/csrf-cookie', { baseURL: '/' });
}

/** @returns {Promise<object>} the logged-in user with roles and permissions */
export async function login(credentials) {
    await getCsrfCookie();
    const { data } = await http.post('/auth/login', credentials);

    return data.data;
}

export async function logout() {
    await http.post('/auth/logout');
}

/** @returns {Promise<object>} the current user; rejects with 401 when logged out */
export async function fetchMe() {
    // The router guard handles "not logged in"; don't trigger the global 401 redirect.
    const { data } = await http.get('/auth/me', { skipAuthRedirect: true });

    return data.data;
}
