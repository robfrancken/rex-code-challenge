import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter, Outlet, type InitialEntry } from 'react-router';
import { toast } from 'sonner';
import { ApiProblem } from '@/lib/api/problem';
import { createContact, getContact, updateContact } from '@/modules/contacts/api/contacts';
import { contactKeys } from '@/modules/contacts/api/keys';
import { makeContact } from '@/modules/contacts/fixtures';
import { ContactSheet } from '@/modules/contacts/routes/ContactSheet';
import { hideRandomUUID } from '@/test/insecureContext';

vi.mock('@/modules/contacts/api/contacts');
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const created = makeContact({ id: 'c0ffee00-0000-4000-8000-0000000000aa', name: 'Dan Brown' });
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;

function testQueryClient() {
    return new QueryClient({ defaultOptions: { queries: { retry: false } } });
}

function renderSheet(initialEntry: InitialEntry, queryClient = testQueryClient()) {
    const router = createMemoryRouter(
        [
            {
                path: '/contacts',
                element: <Outlet />,
                children: [
                    { path: 'new', element: <ContactSheet /> },
                    { path: ':id/edit', element: <ContactSheet /> },
                ],
            },
            { path: '/contacts/:id', element: <p>Detail page</p> },
        ],
        { initialEntries: [initialEntry] },
    );

    render(
        <QueryClientProvider client={queryClient}>
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return router;
}

/** TanStack Query batches observer updates on a timer, so a settled refetch reaches React a tick later. */
function flushQueryNotifications() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

async function fillCreateForm() {
    await userEvent.type(await screen.findByLabelText('Name'), 'Dan Brown');
    await userEvent.type(screen.getByLabelText('Email 1'), 'dan@rex.com.au');
    await userEvent.type(screen.getByLabelText('Phone 1'), '+61412345678');
}

describe('ContactSheet', () => {
    beforeEach(() => vi.resetAllMocks());
    afterEach(() => vi.unstubAllGlobals());

    it('reuses one idempotency key when a failed submit is retried', async () => {
        vi.mocked(createContact)
            .mockRejectedValueOnce(new Error('Network Error'))
            .mockResolvedValueOnce(created);
        renderSheet('/contacts/new');
        await fillCreateForm();

        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));
        await waitFor(() => expect(toast.error).toHaveBeenCalled());
        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        await waitFor(() => expect(createContact).toHaveBeenCalledTimes(2));
        const [firstKey, secondKey] = vi
            .mocked(createContact)
            .mock.calls.map((callArgs) => callArgs[1]);
        expect(firstKey).toMatch(/^[0-9a-f-]{36}$/);
        expect(secondKey).toBe(firstKey);
    });

    it('renders the New contact sheet and creates with a uuid key outside a secure context', async () => {
        hideRandomUUID();
        vi.mocked(createContact).mockResolvedValue(created);
        renderSheet('/contacts/new');
        await fillCreateForm();

        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        await waitFor(() =>
            expect(createContact).toHaveBeenCalledWith(
                expect.anything(),
                expect.stringMatching(UUID),
            ),
        );
    });

    it('navigates to the new contact and toasts on success', async () => {
        vi.mocked(createContact).mockResolvedValue(created);
        const router = renderSheet('/contacts/new');
        await fillCreateForm();

        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        await waitFor(() => expect(router.state.location.pathname).toBe(`/contacts/${created.id}`));
        expect(toast.success).toHaveBeenCalledWith('Contact created');
    });

    it('maps a 422 onto the field and keeps the sheet open', async () => {
        vi.mocked(createContact).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Validation failed',
                status: 422,
                detail: 'Invalid.',
                code: 'validation_failed',
                errors: { 'phones.0': ['Must be an E.164 number.'] },
            }),
        );
        const router = renderSheet('/contacts/new');
        await fillCreateForm();

        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        expect(await screen.findByText('Must be an E.164 number.')).toBeInTheDocument();
        expect(router.state.location.pathname).toBe('/contacts/new');
    });

    it('shows the fetched values in edit mode and saves with updateContact', async () => {
        const existing = makeContact({ phones: ['+61298765432'] });
        vi.mocked(getContact).mockResolvedValue(existing);
        vi.mocked(updateContact).mockResolvedValue({ ...existing, name: 'Chloe Jones' });
        const router = renderSheet(`/contacts/${existing.id}/edit`);

        const name = await screen.findByLabelText('Name');
        expect(name).toHaveValue('Chloe Smith');
        expect(screen.getByLabelText('Email 1')).toHaveValue('chloe@rex.com.au');
        await userEvent.clear(name);
        await userEvent.type(name, 'Chloe Jones');
        await userEvent.click(screen.getByRole('button', { name: 'Save contact' }));

        await waitFor(() =>
            expect(updateContact).toHaveBeenCalledWith(existing.id, {
                name: 'Chloe Jones',
                emails: ['chloe@rex.com.au'],
                phones: ['+61298765432'],
            }),
        );
        await waitFor(() =>
            expect(router.state.location.pathname).toBe(`/contacts/${existing.id}`),
        );
        expect(toast.success).toHaveBeenCalledWith('Contact updated');
    });

    it('keeps the loaded edit form and its typed changes when a background refetch fails', async () => {
        const existing = makeContact();
        vi.mocked(getContact).mockResolvedValue(existing);
        const queryClient = testQueryClient();
        renderSheet(`/contacts/${existing.id}/edit`, queryClient);
        const name = await screen.findByLabelText('Name');
        await userEvent.clear(name);
        await userEvent.type(name, 'Chloe Jones');

        vi.mocked(getContact).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Server error',
                status: 500,
                detail: 'Try later.',
                code: 'server_error',
            }),
        );
        await act(async () => {
            await queryClient.refetchQueries({ queryKey: contactKeys.detail(existing.id) });
            await flushQueryNotifications();
        });

        expect(queryClient.getQueryState(contactKeys.detail(existing.id))?.status).toBe('error');
        expect(screen.getByLabelText('Name')).toHaveValue('Chloe Jones');
        expect(screen.getByRole('button', { name: 'Save contact' })).toBeInTheDocument();
    });

    it('marks the edit form as busy while the contact loads, and titles the page', async () => {
        vi.mocked(getContact).mockReturnValue(new Promise(() => undefined));
        renderSheet('/contacts/c0ffee00-0000-4000-8000-000000000001/edit');

        expect(await screen.findByTestId('edit-skeleton')).toHaveAttribute('aria-busy', 'true');
        expect(document.title).toBe('Edit contact · Rex CRM');
    });

    it('shows the not-found state inside the sheet when editing a missing contact', async () => {
        vi.mocked(getContact).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Not found',
                status: 404,
                detail: 'None.',
                code: 'contact_not_found',
            }),
        );
        renderSheet('/contacts/c0ffee00-0000-4000-8000-000000000009/edit');

        expect(await screen.findByText('Contact not found')).toBeInTheDocument();
    });

    it('closing the sheet returns to /contacts keeping the search params', async () => {
        const router = renderSheet('/contacts/new?name=smi&page=2');

        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(router.state.location.search).toBe('?name=smi&page=2');
    });

    it.each([['//evil.example'], ['https://evil.example'], [42]])(
        'ignores an unsafe returnTo (%s) and closes to the list',
        async (returnTo) => {
            const router = renderSheet({ pathname: '/contacts/new', state: { returnTo } });

            await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

            await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        },
    );

    it('closing an edit opened from the detail page returns to that page', async () => {
        const existing = makeContact();
        vi.mocked(getContact).mockResolvedValue(existing);
        const router = renderSheet({
            pathname: `/contacts/${existing.id}/edit`,
            state: { returnTo: `/contacts/${existing.id}` },
        });

        await userEvent.click(await screen.findByRole('button', { name: 'Close' }));

        await waitFor(() =>
            expect(router.state.location.pathname).toBe(`/contacts/${existing.id}`),
        );
    });
});
