import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RouterProvider, createMemoryRouter } from 'react-router';
import { toast } from 'sonner';
import { ApiProblem } from '@/lib/api/problem';
import { callContact, deleteContact, getContact } from '@/modules/contacts/api/contacts';
import { makeContact } from '@/modules/contacts/fixtures';
import { ContactDetailPage } from '@/modules/contacts/routes/ContactDetailPage';

vi.mock('@/modules/contacts/api/contacts');
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const contact = makeContact({ phones: ['+61298765432', '+61498765432'] });
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/;

function renderDetail() {
    const router = createMemoryRouter(
        [
            { path: '/contacts', element: <p>List page</p> },
            { path: '/contacts/:id', element: <ContactDetailPage /> },
        ],
        { initialEntries: [`/contacts/${contact.id}`] },
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

describe('ContactDetailPage', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        vi.mocked(getContact).mockResolvedValue(contact);
    });

    it('shows the contact with grouped phones and emails', async () => {
        renderDetail();

        expect(await screen.findByRole('heading', { name: 'Chloe Smith' })).toBeInTheDocument();
        expect(screen.getByText('+61 2 9876 5432')).toBeInTheDocument();
        expect(screen.getByText('chloe@rex.com.au')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Edit' })).toHaveAttribute(
            'href',
            `/contacts/${contact.id}/edit`,
        );
    });

    it('calls the second phone with its number and a uuid idempotency key, then shows the outcome', async () => {
        vi.mocked(callContact).mockResolvedValue({
            contact_id: contact.id,
            phone: '+61498765432',
            outcome: 'voicemail',
            placed_at: new Date().toISOString(),
        });
        renderDetail();
        await screen.findByRole('heading', { name: 'Chloe Smith' });

        await userEvent.click(screen.getAllByRole('button', { name: /^Call/ })[1]);

        expect(callContact).toHaveBeenCalledWith(
            contact.id,
            '+61498765432',
            expect.stringMatching(UUID),
        );
        expect(await screen.findByText('Went to voicemail')).toBeInTheDocument();
    });

    it('shows the not-found state for a missing contact', async () => {
        vi.mocked(getContact).mockRejectedValue(
            new ApiProblem({
                type: 't',
                title: 'Not found',
                status: 404,
                detail: 'No such contact.',
                code: 'contact_not_found',
            }),
        );
        renderDetail();

        expect(await screen.findByText('Contact not found')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Back to contacts' })).toHaveAttribute(
            'href',
            '/contacts',
        );
    });

    it('deletes after confirmation, navigates to /contacts and toasts', async () => {
        vi.mocked(deleteContact).mockResolvedValue(undefined);
        const router = renderDetail();
        await userEvent.click(await screen.findByRole('button', { name: 'Delete' }));

        const dialog = await screen.findByRole('alertdialog');
        expect(dialog).toHaveTextContent("Delete Chloe Smith? This can't be undone.");
        await userEvent.click(within(dialog).getByRole('button', { name: 'Delete' }));

        await waitFor(() => expect(router.state.location.pathname).toBe('/contacts'));
        expect(deleteContact).toHaveBeenCalledWith(contact.id);
        expect(toast.success).toHaveBeenCalledWith('Contact deleted');
    });
});
