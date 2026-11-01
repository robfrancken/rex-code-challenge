import { redirect, type LoaderFunction } from 'react-router';
import type { QueryClient } from '@tanstack/react-query';
import { isApiProblem } from '@/lib/api/problem';
import { loginUrl } from '@/modules/auth/loginUrl';
import { meQuery } from '@/modules/auth/meQuery';

/** Route loader: resolves the current user, or redirects to login on a 401. */
export function requireAuth(queryClient: QueryClient): LoaderFunction {
    return async ({ request }) => {
        try {
            return await queryClient.ensureQueryData(meQuery);
        } catch (error) {
            if (isApiProblem(error) && error.status === 401) {
                const { pathname, search } = new URL(request.url);

                throw redirect(loginUrl(pathname + search));
            }

            throw error;
        }
    };
}
