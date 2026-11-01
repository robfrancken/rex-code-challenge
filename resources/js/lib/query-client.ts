import { QueryClient } from '@tanstack/react-query';
import { isApiProblem } from '@/lib/api/problem';

const MAX_RETRIES = 2;

export function createQueryClient(): QueryClient {
    return new QueryClient({
        defaultOptions: {
            queries: {
                staleTime: 30_000,
                retry: (failureCount, error) => {
                    if (isApiProblem(error) && error.status < 500) {
                        return false;
                    }

                    return failureCount < MAX_RETRIES;
                },
            },
            mutations: { retry: false },
        },
    });
}
