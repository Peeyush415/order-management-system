import http from './http';

/** params: { unread?: 1, page?, per_page? } → { data, meta } */
export async function fetchNotifications(params) {
    const { data } = await http.get('/notifications', { params });

    return data;
}

export async function fetchUnreadCount() {
    const { data } = await http.get('/notifications/unread-count');

    return data.data.count;
}

export async function markNotificationRead(id) {
    const { data } = await http.post(`/notifications/${id}/read`);

    return data.data;
}

export async function markAllNotificationsRead() {
    const { data } = await http.post('/notifications/read-all');

    return data.data;
}
