import http from './http';

/** GET /api/v1/health → envelope `data` ({ status, version, time }). */
export async function fetchHealth() {
    const { data } = await http.get('/health');

    return data.data;
}
