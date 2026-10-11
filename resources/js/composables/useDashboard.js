import { keepPreviousData, useQuery } from '@tanstack/vue-query';
import * as api from '@/api/dashboard';

// The server caches these for 5 minutes, so the browser doesn't ask again sooner.
const STALE = 5 * 60 * 1000;

export function useDashboardSummary(range, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['dashboard', 'summary', range],
        queryFn: ({ queryKey }) => api.fetchSummary(queryKey[2]),
        staleTime: STALE,
        placeholderData: keepPreviousData,
        enabled,
    });
}

export function useRevenueTrend(params, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['dashboard', 'trend', params],
        queryFn: ({ queryKey }) => api.fetchRevenueTrend(queryKey[2]),
        staleTime: STALE,
        placeholderData: keepPreviousData,
        enabled,
    });
}

export function useStatusDistribution(range, { enabled = () => true } = {}) {
    return useQuery({
        queryKey: ['dashboard', 'status', range],
        queryFn: ({ queryKey }) => api.fetchStatusDistribution(queryKey[2]),
        staleTime: STALE,
        placeholderData: keepPreviousData,
        enabled,
    });
}

export function useMyWork({ enabled = () => true } = {}) {
    return useQuery({ queryKey: ['dashboard', 'my-work'], queryFn: api.fetchMyWork, enabled });
}
