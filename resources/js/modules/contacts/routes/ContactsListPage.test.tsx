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

const SEARCH = 'Search by name, phone or email domain';

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

function validationProblem(errors: Record<string, string[]>) {
    return new ApiProblem({
        type: 't',
        title: 'Validation failed',
        status: 422,
        detail: Object.values(errors)[0][0],
        code: 'validation_failed',
        errors,
    });
}

async function typeAndDebounce(label: string, value: string) {
    fireEvent.change(screen.getByLabelText(label), { target: { value } });
    await act(() => vi.advanceTimersByTimeAsync(300));
}

describe('ContactsListPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(searchContacts).mockResolvedValue(makePage([chloe, marcus]));
    });

    afterEach(() => vi.useRealTimers());

    it('marks the table body as busy while the first page loads', async () => {
        vi.mocked(searchContacts).mockReturnValue(new Promise(() => undefined));
        renderList();

        const [firstSkeletonRow] = await screen.findAllByTestId('skeleton-row');
        expect(firstSkeletonRow.closest('tbody')).toHaveAttribute('aria-busy', 'true');
    });

    it('titles the page, hands the title to an open sheet and takes it back on close', async () => {
        renderList();
        await screen.findByText('Chloe Smith');
        expect(document.title).toBe('Contacts · Rex CRM');

        await userEvent.click(screen.getByRole('link', { name: 'New contact' }));
        await waitFor(() => expect(document.title).toBe('New contact · Rex CRM'));

        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));
        await waitFor(() => expect(document.title).toBe('Contacts · Rex CRM'));
    });

    it('renders the rows with formatted phones and counts, and no totals', async () => {
        renderList();

        expect(await screen.findByText('Chloe Smith')).toBeInTheDocument();
        expect(screen.getByText('+61 2 9876 5432')).toBeInTheDocument();
        expect(screen.getByText('+64 21 234 5678')).toBeInTheDocument();
        expect(screen.getByText('2 emails · 2 phones')).toBeInTheDocument();
        expect(screen.queryByText(/Showing/)).not.toBeInTheDocument();
        expect(screen.queryByText(/\d+ contacts?$/)).not.toBeInTheDocument();
        expect(
            screen.getByText('Search by name, phone or email domain', { selector: 'p' }),
        ).toBeInTheDocument();
    });

    it('asks for 25 contacts a page', async () => {
        renderList('/contacts?q=smi&cursor=abc');
        await screen.findByText('Chloe Smith');

        expect(searchContacts).toHaveBeenCalledWith({ q: 'smi', cursor: 'abc', per_page: 25 });
    });

    it('writes q to the url after the debounce, with no cursor', async () => {
        const router = renderList('/contacts?cursor=abc');
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        fireEvent.change(screen.getByLabelText(SEARCH), { target: { value: 'smi' } });
        expect(router.state.location.search).toBe('?cursor=abc');
        await act(() => vi.advanceTimersByTimeAsync(300));

        expect(router.state.location.search).toBe('?q=smi');
    });

    it('replaces the history entry for typed text, so Back skips the keystrokes', async () => {
        const router = renderList();
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        await typeAndDebounce(SEARCH, 'smi');

        expect(router.state.location.search).toBe('?q=smi');
        expect(router.state.historyAction).toBe('REPLACE');
    });

    it('shows the domain chip when a domain badge is clicked, and removing it clears email_domain', async () => {
        const router = renderList('/contacts?q=smi&cursor=abc');
        await screen.findByText('Chloe Smith');

        await userEvent.click(screen.getByRole('button', { name: 'Filter by rex.com.au' }));

        expect(router.state.location.search).toBe('?q=smi&email_domain=rex.com.au');
        expect(router.state.historyAction).toBe('PUSH');
        expect(screen.getByText('Domain: rex.com.au')).toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Remove domain filter' }));

        expect(router.state.location.search).toBe('?q=smi');
        expect(screen.queryByText('Domain: rex.com.au')).not.toBeInTheDocument();
    });

    it('navigates to the detail page when a row is clicked', async () => {
        const router = renderList();

        await userEvent.click(await screen.findByText('+61 2 9876 5432'));

        expect(router.state.location.pathname).toBe(`/contacts/${chloe.id}`);
    });

    it('pages with Next and Previous by cursor, and Back returns to the earlier page', async () => {
        vi.mocked(searchContacts).mockImplementation(async (params) =>
            makePage([chloe], {
                next_cursor: params.cursor === undefined ? 'next-1' : null,
                prev_cursor: params.cursor === undefined ? null : 'prev-1',
            }),
        );
        const router = renderList('/contacts?q=smi');
        const previous = await screen.findByRole('button', { name: 'Previous' });
        expect(previous).toBeDisabled();

        await userEvent.click(screen.getByRole('button', { name: 'Next' }));
        expect(router.state.location.search).toBe('?q=smi&cursor=next-1');
        await waitFor(() => expect(screen.getByRole('button', { name: 'Next' })).toBeDisabled());

        await userEvent.click(screen.getByRole('button', { name: 'Previous' }));
        expect(router.state.location.search).toBe('?q=smi&cursor=prev-1');

        await act(() => router.navigate(-2));
        expect(router.state.location.search).toBe('?q=smi');
    });

    it('Back after Next returns to ?q=smi', async () => {
        vi.mocked(searchContacts).mockResolvedValue(makePage([chloe], { next_cursor: 'next-1' }));
        const router = renderList('/contacts?q=smi');

        await userEvent.click(await screen.findByRole('button', { name: 'Next' }));
        expect(router.state.location.search).toBe('?q=smi&cursor=next-1');
        await act(() => router.navigate(-1));

        expect(router.state.location.search).toBe('?q=smi');
    });

    it('shows the empty state and Clear removes every param', async () => {
        vi.mocked(searchContacts).mockResolvedValue(makePage([]));
        const router = renderList('/contacts?q=zzz&email_domain=x.com&cursor=abc');

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
        const router = renderList('/contacts?q=smi&cursor=abc');

        await userEvent.click(
            await screen.findByRole('button', { name: 'Actions for Chloe Smith' }),
        );
        await userEvent.click(await screen.findByRole('menuitem', { name: 'Delete' }));
        const dialog = await screen.findByRole('alertdialog');
        await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

        await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
        expect(deleteContact).toHaveBeenCalledWith(chloe.id);
        expect(router.state.location.pathname).toBe('/contacts');
        expect(router.state.location.search).toBe('?q=smi&cursor=abc');
    });

    it('keeps the filters when the New contact sheet is opened and closed', async () => {
        const router = renderList('/contacts?q=smi&cursor=abc');

        await userEvent.click(await screen.findByRole('link', { name: 'New contact' }));
        expect(router.state.location.pathname).toBe('/contacts/new');
        expect(router.state.location.search).toBe('?q=smi&cursor=abc');
        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(router.state.location.search).toBe('?q=smi&cursor=abc');
    });

    it('keeps the filters when a row Edit sheet is opened and closed', async () => {
        vi.mocked(getContact).mockResolvedValue(chloe);
        const router = renderList('/contacts?q=smi&cursor=abc');

        await userEvent.click(
            await screen.findByRole('button', { name: 'Actions for Chloe Smith' }),
        );
        await userEvent.click(await screen.findByRole('menuitem', { name: 'Edit' }));
        expect(router.state.location.pathname).toBe(`/contacts/${chloe.id}/edit`);
        expect(router.state.location.search).toBe('?q=smi&cursor=abc');
        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(router.state.location.search).toBe('?q=smi&cursor=abc');
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

    it('shows a 422 on q under the search box, keeps the rows and offers no Retry', async () => {
        vi.mocked(searchContacts).mockImplementation(async (params) => {
            if (params.q) {
                throw validationProblem({
                    q: ['The q field must not be greater than 255 characters.'],
                });
            }

            return makePage([chloe, marcus]);
        });
        renderList();
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        await typeAndDebounce(SEARCH, 'abc');

        const box = screen.getByLabelText(SEARCH);
        await waitFor(() =>
            expect(box).toHaveAccessibleDescription(
                'The q field must not be greater than 255 characters.',
            ),
        );
        expect(box).toBeInvalid();
        expect(screen.getByText('Chloe Smith')).toBeInTheDocument();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument();
    });

    it('shows a 422 on cursor under the search box', async () => {
        vi.mocked(searchContacts).mockRejectedValue(
            validationProblem({ cursor: ['The cursor is not a valid page cursor.'] }),
        );
        renderList('/contacts?cursor=bad');

        expect(await screen.findByText('The cursor is not a valid page cursor.')).toBeVisible();
        expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('shows a 422 on a key with no input in the alert, without Retry', async () => {
        vi.mocked(searchContacts).mockRejectedValue(
            validationProblem({ per_page: ['The per page field must not be greater than 100.'] }),
        );
        renderList();

        expect(await screen.findByRole('alert')).toHaveTextContent('Validation failed');
        expect(screen.queryByRole('button', { name: 'Retry' })).not.toBeInTheDocument();
    });

    it('does not re-apply a pending draft after Clear', async () => {
        const router = renderList('/contacts?q=abc');
        await screen.findByText('Chloe Smith');
        vi.useFakeTimers({ shouldAdvanceTime: true });

        fireEvent.change(screen.getByLabelText(SEARCH), { target: { value: 'abcd' } });
        fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
        await act(() => vi.advanceTimersByTimeAsync(300));

        expect(router.state.location.search).toBe('');
        expect(screen.getByLabelText(SEARCH)).toHaveValue('');
    });
});
