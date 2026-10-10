import { useQuery } from '@tanstack/vue-query';
import { fetchHealth } from '@/api/health';

export function useHealth() {
    return useQuery({
        queryKey: ['health'],
        queryFn: fetchHealth,
    });
}
