import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { ContactsTable } from '@/modules/contacts/components/ContactsTable';
import { makeContact } from '@/modules/contacts/fixtures';

function renderTable() {
    render(
        <MemoryRouter>
            <ContactsTable
                contacts={[makeContact()]}
                isLoading={false}
                onSelectDomain={vi.fn()}
                onDelete={vi.fn()}
            />
        </MemoryRouter>,
    );
}

describe('ContactsTable responsive columns', () => {
    it('hides the Email and Phone columns below md, header and cells alike', () => {
        renderTable();

        expect(screen.getByRole('columnheader', { name: 'Email' })).toHaveClass(
            'hidden',
            'md:table-cell',
        );
        expect(screen.getByRole('columnheader', { name: 'Phone' })).toHaveClass(
            'hidden',
            'md:table-cell',
        );
        const cells = screen.getAllByRole('cell');
        expect(cells[1]).toHaveClass('hidden', 'md:table-cell');
        expect(cells[2]).toHaveClass('hidden', 'md:table-cell');
    });

    it('keeps the contact name and the actions menu visible', () => {
        renderTable();

        expect(screen.getByRole('columnheader', { name: 'Contact' })).not.toHaveClass('hidden');
        const [nameCell, , , actionsCell] = screen.getAllByRole('cell');
        expect(nameCell).not.toHaveClass('hidden');
        expect(actionsCell).not.toHaveClass('hidden');
        expect(nameCell).toHaveTextContent('1 email · 1 phone');
    });

    it('gives rows a touch-friendly minimum height', () => {
        renderTable();

        expect(screen.getByRole('link', { name: 'Chloe Smith' }).closest('tr')).toHaveClass('h-14');
    });

    it('truncates a long name inside its cell so the actions menu stays on screen', () => {
        renderTable();

        const link = screen.getByRole('link', { name: 'Chloe Smith' });
        expect(link).toHaveClass('block', 'truncate');
        expect(link.parentElement).toHaveClass('min-w-0');
        expect(link.closest('td')).toHaveClass('max-w-0', 'w-full');
    });
});
