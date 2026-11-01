import type { LoaderFunctionArgs } from 'react-router';
import { QueryClient } from '@tanstack/react-query';
import { ApiProblem } from '@/lib/api/problem';
import { fetchMe } from '@/modules/auth/api';
import { requireAuth } from '@/modules/auth/requireAuth';

vi.mock('@/modules/auth/api', () => ({
    meQueryKey: ['auth', 'me'],
    fetchMe: vi.fn(),
}));

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' };

function args(url: string): LoaderFunctionArgs {
    return { request: new Request(url), params: {}, context: {} } as LoaderFunctionArgs;
}

describe('requireAuth', () => {
    it('redirects to login with the current path and query when the API answers 401', async () => {
        vi.mocked(fetchMe).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Unauthenticated',
                status: 401,
                detail: 'No session.',
                code: 'unauthenticated',
            }),
        );

        const thrown = await Promise.resolve(
            requireAuth(new QueryClient())(args('http://localhost/contacts?page=2')),
        ).catch((e: unknown) => e);

        expect(thrown).toBeInstanceOf(Response);
        expect((thrown as Response).status).toBe(302);
        expect((thrown as Response).headers.get('Location')).toBe(
            '/login?redirect=%2Fcontacts%3Fpage%3D2',
        );
    });

    it('returns the user when signed in', async () => {
        vi.mocked(fetchMe).mockResolvedValue(user);

        await expect(
            requireAuth(new QueryClient())(args('http://localhost/contacts')),
        ).resolves.toEqual(user);
    });

    it('rethrows errors other than 401', async () => {
        const boom = new ApiProblem({
            type: 't',
            title: 'Server error',
            status: 500,
            detail: 'Oops.',
            code: 'server_error',
        });
        vi.mocked(fetchMe).mockRejectedValue(boom);

        await expect(
            requireAuth(new QueryClient())(args('http://localhost/contacts')),
        ).rejects.toBe(boom);
    });
});
