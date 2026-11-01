import { useSuspenseQuery } from '@tanstack/react-query';
import { meQuery } from '@/modules/auth/meQuery';
import type { User } from '@/modules/auth/api';

/** The signed-in user. The `requireAuth` loader fills this cache before any protected route renders. */
export function useCurrentUser(): User {
    return useSuspenseQuery(meQuery).data;
}
