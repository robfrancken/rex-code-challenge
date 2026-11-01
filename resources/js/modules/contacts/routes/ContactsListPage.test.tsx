import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { deleteContact, getContact, searchContacts } from '@/modules/contacts/api/contacts';
import { makeContact, makePage } from '@/modules/contacts/fixtures';
import { ContactSheet } from '@/modules/contacts/routes/ContactSheet';
import { ContactsListPage } from '@/modules/contacts/routes/ContactsListPage';
import { ApiProblem } from '@/lib/api/problem';

vi.mock('@/modules/contacts/api/contacts');
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const chloe = makeContact();
const marcus = makeContact({
    id: 'c0ffee00-0000-4000-8000-000000000002',
    name: 'Marcus Patel',
    emails: ['m.patel@example.co.nz', 'marcus@rex.com.au'],
    phones: ['+64212345678', '+61412345678'],
});

function renderList(initialEntry = '/contacts') {
    const router = createMemoryRouter(
        [
            {
                path: '/contacts',
                element: <ContactsListPage />,
                children: [
                    { path: 'new', element: <ContactSheet /> },
                    { path: ':id/edit', element: <ContactSheet /> },
                ],
            },
            { path: '/contacts/:id', element: <p>Detail page</p> },
        ],
        { initialEntries: [initialEntry] },
    );
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

    render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return router;
}

async function typeAndDebounce(label: string, value: string) {
    fireEvent.change(screen.getByLabelText(label), { target: { value } });
    await act(() => vi.advanceTimersByTimeAsync(300));
}

describe('ContactsListPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(searchContacts).mockResolvedValue(
            makePage([chloe, marcus], { total: 40, to: 2 }),
        );
    });

    afterEach(() => vi.useRealTimers());

    it('renders the rows with formatted phones, counts and the total', async () => {
        renderList();

        expect(await screen.findByText('Chloe Smith')).toBeInTheDocument();
        expect(screen.getByText('+61 2 9876 5432')).toBeInTheDocument();
        expect(screen.getByText('+64 21 234 5678')).toBeInTheDocument();
        expect(screen.getByText('2 emails · 2 phones')).toBeInTheDocument();
        expect(screen.getByText('40 contacts')).toBeInTheDocument();
        expect(screen.getByText('Showing 1–2 of 40')).toBeInTheDocument();
    });

    it('writes the name filter to the url after the debounce', async () => {
        const router = renderList();
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'smi' } });
        expect(router.state.location.search).toBe('');
        await act(() => vi.advanceTimersByTimeAsync(300));

        expect(router.state.location.search).toBe('?name=smi');
    });

    it('drops the page param when a filter changes', async () => {
        const router = renderList('/contacts?page=3&name=ch');
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        await typeAndDebounce('Email domain', 'rex.com.au');

        expect(router.state.location.search).toBe('?name=ch&email_domain=rex.com.au');
    });

    it('applies the email_domain filter when a domain badge is clicked', async () => {
        const router = renderList('/contacts?page=2');
        await screen.findByText('Chloe Smith');

        await userEvent.click(screen.getByRole('button', { name: 'Filter by rex.com.au' }));

        expect(router.state.location.search).toBe('?email_domain=rex.com.au');
        expect(screen.getByLabelText('Email domain')).toHaveValue('rex.com.au');
    });

    it('navigates to the detail page when a row is clicked', async () => {
        const router = renderList();

        await userEvent.click(await screen.findByText('+61 2 9876 5432'));

        expect(router.state.location.pathname).toBe(`/contacts/${chloe.id}`);
    });

    it('pages with Next and Previous', async () => {
        vi.mocked(searchContacts).mockImplementation(async (params) =>
            makePage([chloe], {
                current_page: params.page ?? 1,
                last_page: 3,
                from: 16,
                to: 16,
                total: 31,
            }),
        );
        const router = renderList('/contacts?page=2');

        await userEvent.click(await screen.findByRole('button', { name: 'Next' }));
        expect(router.state.location.search).toBe('?page=3');

        await userEvent.click(screen.getByRole('button', { name: 'Previous' }));
        expect(router.state.location.search).toBe('?page=2');
    });

    it('shows the empty state and Clear removes every param', async () => {
        vi.mocked(searchContacts).mockResolvedValue(makePage([]));
        const router = renderList('/contacts?name=zzz&phone=04&email_domain=x.com&page=2');

        expect(await screen.findByText('No contacts match your search')).toBeInTheDocument();
        await userEvent.click(screen.getAllByRole('button', { name: 'Clear' })[0]);

        expect(router.state.location.search).toBe('');
    });

    it('opens the delete confirmation from the row menu', async () => {
        renderList();

        await userEvent.click(
            await screen.findByRole('button', { name: 'Actions for Chloe Smith' }),
        );
        await userEvent.click(await screen.findByRole('menuitem', { name: 'Delete' }));

        expect(await screen.findByRole('alertdialog')).toHaveTextContent(
            "Delete Chloe Smith? This can't be undone.",
        );
    });

    it('closes the delete dialog after a successful delete and keeps the url and filters', async () => {
        vi.mocked(deleteContact).mockResolvedValue(undefined);
        const router = renderList('/contacts?name=smi&page=2');

        await userEvent.click(
            await screen.findByRole('button', { name: 'Actions for Chloe Smith' }),
        );
        await userEvent.click(await screen.findByRole('menuitem', { name: 'Delete' }));
        const dialog = await screen.findByRole('alertdialog');
        await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

        await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
        expect(deleteContact).toHaveBeenCalledWith(chloe.id);
        expect(router.state.location.pathname).toBe('/contacts');
        expect(router.state.location.search).toBe('?name=smi&page=2');
    });

    it('keeps the filters when the New contact sheet is opened and closed', async () => {
        const router = renderList('/contacts?name=smi&page=2');

        await userEvent.click(await screen.findByRole('link', { name: 'New contact' }));
        expect(router.state.location.pathname).toBe('/contacts/new');
        expect(router.state.location.search).toBe('?name=smi&page=2');
        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(router.state.location.search).toBe('?name=smi&page=2');
    });

    it('keeps the filters when a row Edit sheet is opened and closed', async () => {
        vi.mocked(getContact).mockResolvedValue(chloe);
        const router = renderList('/contacts?name=smi&page=2');

        await userEvent.click(
            await screen.findByRole('button', { name: 'Actions for Chloe Smith' }),
        );
        await userEvent.click(await screen.findByRole('menuitem', { name: 'Edit' }));
        expect(router.state.location.pathname).toBe(`/contacts/${chloe.id}/edit`);
        expect(router.state.location.search).toBe('?name=smi&page=2');
        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(router.state.location.search).toBe('?name=smi&page=2');
    });

    it('shows an error alert with the problem text and Retry queries again', async () => {
        vi.mocked(searchContacts).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Server error',
                status: 500,
                detail: 'The server fell over.',
                code: 'server_error',
            }),
        );
        renderList();

        expect(await screen.findByRole('alert')).toHaveTextContent('The server fell over.');
        expect(searchContacts).toHaveBeenCalledTimes(1);
        await userEvent.click(screen.getByRole('button', { name: 'Retry' }));

        await waitFor(() => expect(searchContacts).toHaveBeenCalledTimes(2));
    });

    it('does not re-apply a pending draft after Clear', async () => {
        const router = renderList('/contacts?name=abc');
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'abcd' } });
        fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
        await act(() => vi.advanceTimersByTimeAsync(300));

        expect(router.state.location.search).toBe('');
        expect(screen.getByLabelText('Name')).toHaveValue('');
    });
});
