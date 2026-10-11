import http from './http';

/** [{ id, name, label, locked, users_count, permissions: [name] }] */
export async function fetchRoles() {
    const { data } = await http.get('/roles');

    return data.data;
}

/** [{ group, label, permissions: [{ name, label }] }] */
export async function fetchPermissions() {
    const { data } = await http.get('/permissions');

    return data.data;
}

export async function updateRolePermissions(id, permissions) {
    const { data } = await http.put(`/roles/${id}/permissions`, { permissions });

    return data.data;
}
