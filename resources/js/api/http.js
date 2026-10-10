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

export default http;
