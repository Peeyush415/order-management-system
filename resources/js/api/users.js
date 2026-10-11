import http from './http';

/** @returns {Promise<{ data: object[], meta: object }>} */
export async function fetchUsers(params) {
    const { data } = await http.get('/users', { params });

    return data;
}

export async function fetchUser(id) {
    const { data } = await http.get(`/users/${id}`);

    return data.data;
}

export async function createUser(payload) {
    const { data } = await http.post('/users', payload);

    return data.data;
}

export async function updateUser(id, payload) {
    const { data } = await http.put(`/users/${id}`, payload);

    return data.data;
}

export async function changeUserStatus(id, isActive) {
    const { data } = await http.patch(`/users/${id}/status`, { is_active: isActive });

    return data.data;
}
