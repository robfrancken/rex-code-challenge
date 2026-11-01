import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { toast } from 'sonner';
import { AppShell } from '@/components/AppShell';
import { ApiProblem } from '@/lib/api/problem';
import { logout, meQueryKey } from '@/modules/auth/api';

vi.mock('@/modules/auth/api', () => ({
    meQueryKey: ['auth', 'me'],
    logout: vi.fn(),
    fetchMe: vi.fn(),
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' };

function renderShell() {
    const queryClient = new QueryClient({ defaultOptions: { queries: { staleTime: Infinity } } });
    queryClient.setQueryData(meQueryKey, user);
    const router = createMemoryRouter(
        [
            {
                path: '/',
                element: <AppShell />,
                children: [{ path: 'contacts', element: <p>Contacts page</p> }],
            },
            { path: '/login', element: <p>Login page</p> },
        ],
        { initialEntries: ['/contacts'] },
    );

    render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return { router, queryClient };
}

describe('AppShell sign out', () => {
    beforeEach(() => vi.resetAllMocks());

    it('logs out, navigates to /login and clears the cache', async () => {
        vi.mocked(logout).mockResolvedValue(undefined);
        const { router, queryClient } = renderShell();

        await userEvent.click(screen.getByRole('button', { name: 'Sign out' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
        expect(queryClient.getQueryData(meQueryKey)).toBeUndefined();
    });

    it('treats a 401 from logout as already signed out, without an error toast', async () => {
        vi.mocked(logout).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Unauthenticated',
                status: 401,
                detail: 'No session.',
                code: 'unauthenticated',
            }),
        );
        const { router, queryClient } = renderShell();

        await userEvent.click(screen.getByRole('button', { name: 'Sign out' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
        expect(queryClient.getQueryData(meQueryKey)).toBeUndefined();
        expect(toast.error).not.toHaveBeenCalled();
    });

    it('stays put and shows a toast when logout fails', async () => {
        vi.mocked(logout).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Server error',
                status: 500,
                detail: 'Try later.',
                code: 'server_error',
            }),
        );
        const { router } = renderShell();

        await userEvent.click(screen.getByRole('button', { name: 'Sign out' }));

        await waitFor(() =>
            expect(toast.error).toHaveBeenCalledWith('Server error', { description: 'Try later.' }),
        );
        expect(router.state.location.pathname).toBe('/contacts');
    });
});

describe('AppShell layout', () => {
    it('pins the sidebar to the viewport so sign-out stays visible while the content scrolls', () => {
        renderShell();

        const sidebar = screen.getByRole('button', { name: 'Sign out' }).closest('aside');
        expect(sidebar).toHaveClass('sticky', 'top-0', 'h-svh', 'self-start');
        expect(screen.getByRole('main')).toHaveClass('min-w-0');
    });
});
