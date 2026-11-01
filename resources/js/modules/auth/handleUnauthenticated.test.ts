import { QueryClient } from '@tanstack/react-query';
import { meQueryKey } from '@/modules/auth/api';
import { createUnauthenticatedHandler } from '@/modules/auth/handleUnauthenticated';

vi.mock('@/modules/auth/api', () => ({ meQueryKey: ['auth', 'me'] }));

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' };

describe('createUnauthenticatedHandler', () => {
    it('clears the cached user and navigates to login with the current location', () => {
        const queryClient = new QueryClient();
        queryClient.setQueryData(meQueryKey, user);
        const navigate = vi.fn();

        createUnauthenticatedHandler(queryClient, navigate, () => '/contacts/1?x=1')();

        expect(queryClient.getQueryData(meQueryKey)).toBeUndefined();
        expect(navigate).toHaveBeenCalledExactlyOnceWith('/login?redirect=%2Fcontacts%2F1%3Fx%3D1');
    });

    it('does nothing when no user is cached, so the requireAuth loader redirects alone', () => {
        const navigate = vi.fn();

        createUnauthenticatedHandler(new QueryClient(), navigate, () => '/contacts')();

        expect(navigate).not.toHaveBeenCalled();
    });

    it('never redirects while already on the login page', () => {
        const queryClient = new QueryClient();
        queryClient.setQueryData(meQueryKey, user);
        const navigate = vi.fn();

        createUnauthenticatedHandler(queryClient, navigate, () => '/login?redirect=%2Fcontacts')();

        expect(navigate).not.toHaveBeenCalled();
    });

    it('still redirects from a path that merely starts with /login', () => {
        const queryClient = new QueryClient();
        queryClient.setQueryData(meQueryKey, user);
        const navigate = vi.fn();

        createUnauthenticatedHandler(queryClient, navigate, () => '/login-help')();

        expect(navigate).toHaveBeenCalledOnce();
    });
});
