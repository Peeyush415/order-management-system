import http from './http';

/** params: { user_id?, event?, auditable_type?, auditable_id?, from?, to?, page? } → { data, meta } */
export async function fetchAuditLogs(params) {
    const { data } = await http.get('/audit-logs', { params });

    return data;
}
