import type { QueryClient } from '@tanstack/react-query';
import { meQueryKey } from '@/modules/auth/api';
import { loginUrl } from '@/modules/auth/loginUrl';

function isLoginPath(location: string): boolean {
    return new URL(location, 'http://localhost').pathname === '/login';
}

/**
 * Builds the global 401 handler. It acts only when a user was cached (the session expired mid-use),
 * so the `requireAuth` loader owns the redirect for a 401 on first load, and it never fires on /login.
 */
export function createUnauthenticatedHandler(
    queryClient: QueryClient,
    navigate: (to: string) => unknown,
    currentLocation: () => string,
): () => void {
    return () => {
        const location = currentLocation();

        if (isLoginPath(location) || queryClient.getQueryData(meQueryKey) === undefined) {
            return;
        }

        queryClient.removeQueries({ queryKey: meQueryKey });
        void navigate(loginUrl(location));
    };
}
