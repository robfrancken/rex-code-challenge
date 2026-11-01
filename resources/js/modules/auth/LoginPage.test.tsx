import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { ApiProblem } from '@/lib/api/problem';
import { login } from '@/modules/auth/api';
import { LoginPage } from '@/modules/auth/LoginPage';

vi.mock('@/modules/auth/api', () => ({
    meQueryKey: ['auth', 'me'],
    login: vi.fn(),
}));

const user = { id: 1, name: 'Demo User', email: 'demo@example.com' };

function setEnv(value: string) {
    document.head.innerHTML = `<meta name="app-env" content="${value}">`;
}

function renderLogin(initialEntry = '/login') {
    const router = createMemoryRouter(
        [
            { path: '/login', element: <LoginPage /> },
            { path: '/contacts', element: <p>Contacts page</p> },
            { path: '/contacts/7', element: <p>Contact seven</p> },
        ],
        { initialEntries: [initialEntry] },
    );

    render(
        <QueryClientProvider client={new QueryClient()}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return router;
}

async function submit() {
    await userEvent.type(screen.getByLabelText('Email'), 'demo@example.com');
    await userEvent.type(screen.getByLabelText('Password'), 'password');
    await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));
}

describe('LoginPage', () => {
    beforeEach(() => {
        vi.mocked(login).mockReset();
        setEnv('local');
    });

    it('logs in and navigates to the safe redirect', async () => {
        vi.mocked(login).mockResolvedValue(user);
        const router = renderLogin('/login?redirect=%2Fcontacts%2F7');

        await submit();

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts/7'));
        expect(login).toHaveBeenCalledWith({ email: 'demo@example.com', password: 'password' });
    });

    it('ignores an unsafe redirect and lands on /contacts', async () => {
        vi.mocked(login).mockResolvedValue(user);
        const router = renderLogin('/login?redirect=%2F%2Fevil.example');

        await submit();

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
    });

    it('shows the detail of an invalid_credentials problem', async () => {
        vi.mocked(login).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Invalid credentials',
                status: 422,
                detail: 'These credentials do not match our records.',
                code: 'invalid_credentials',
            }),
        );
        renderLogin();

        await submit();

        expect(
            await screen.findByText('These credentials do not match our records.'),
        ).toBeInTheDocument();
    });

    it('puts validation errors from an empty submit under the email and password inputs', async () => {
        vi.mocked(login).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Validation failed',
                status: 422,
                detail: 'The email field is required. (and 1 more error)',
                code: 'validation_failed',
                errors: {
                    email: ['The email field is required.'],
                    password: ['The password field is required.'],
                },
            }),
        );
        renderLogin();

        await userEvent.click(screen.getByRole('button', { name: 'Sign in' }));

        const email = screen.getByLabelText('Email');
        await waitFor(() =>
            expect(email).toHaveAccessibleDescription('The email field is required.'),
        );
        expect(email).toBeInvalid();
        const password = screen.getByLabelText('Password');
        expect(password).toHaveAccessibleDescription('The password field is required.');
        expect(password).toBeInvalid();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('shows a rate limit message with the retry seconds', async () => {
        vi.mocked(login).mockRejectedValue(
            new ApiProblem(
                {
                    type: 't',
                    title: 'Slow down',
                    status: 429,
                    detail: 'Too many.',
                    code: 'too_many_requests',
                },
                30,
            ),
        );
        renderLogin();

        await submit();

        expect(await screen.findByText(/Too many attempts, try again shortly/)).toHaveTextContent(
            '30',
        );
    });

    it('hides the demo hint in production', () => {
        setEnv('production');
        renderLogin();

        expect(screen.queryByText(/Demo account/)).not.toBeInTheDocument();
    });

    it('shows the demo hint outside production', () => {
        setEnv('local');
        renderLogin();

        expect(screen.getByText(/Demo account: demo@example.com \/ password/)).toBeInTheDocument();
    });
});
