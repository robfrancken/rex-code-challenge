import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter, Outlet } from 'react-router';
import { toast } from 'sonner';
import { ApiProblem } from '@/lib/api/problem';
import { createContact, getContact, updateContact } from '@/modules/contacts/api/contacts';
import { makeContact } from '@/modules/contacts/fixtures';
import { ContactSheet } from '@/modules/contacts/routes/ContactSheet';

vi.mock('@/modules/contacts/api/contacts');
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const created = makeContact({ id: 'c0ffee00-0000-4000-8000-0000000000aa', name: 'Dan Brown' });

function renderSheet(initialEntry: string) {
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
        <QueryClientProvider
            client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}
        >
            <RouterProvider router={router} />
        </QueryClientProvider>,
    );

    return router;
}

async function fillCreateForm() {
    await userEvent.type(await screen.findByLabelText('Name'), 'Dan Brown');
    await userEvent.type(screen.getByLabelText('Email 1'), 'dan@rex.com.au');
    await userEvent.type(screen.getByLabelText('Phone 1'), '+61412345678');
}

describe('ContactSheet', () => {
    beforeEach(() => vi.resetAllMocks());

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
});
