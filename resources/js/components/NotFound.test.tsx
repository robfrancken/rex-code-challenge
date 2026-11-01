import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { NotFound } from '@/components/NotFound';

describe('NotFound', () => {
    it('links back to the contacts and titles the page', () => {
        render(
            <MemoryRouter>
                <NotFound />
            </MemoryRouter>,
        );

        expect(screen.getByRole('link', { name: 'Back to contacts' })).toHaveAttribute(
            'href',
            '/contacts',
        );
        expect(document.title).toBe('Page not found · Rex CRM');
    });
});
