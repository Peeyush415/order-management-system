import { useQuery } from '@tanstack/vue-query';
import { fetchLookups } from '@/api/lookups';

/** Statuses, priorities and assignable users. They rarely change, so they stay fresh for 5 minutes. */
export function useLookups() {
    return useQuery({
        queryKey: ['lookups'],
        queryFn: fetchLookups,
        staleTime: 5 * 60 * 1000,
    });
}
