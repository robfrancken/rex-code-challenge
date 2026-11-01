import { render, screen } from '@testing-library/react';
import { ContactSearch } from '@/modules/contacts/components/ContactSearch';

describe('ContactSearch domain chip', () => {
    it('stays inside the card and truncates a long domain', () => {
        render(
            <ContactSearch
                query=""
                emailDomain="a-very-long-subdomain.example-company.com.au"
                error={undefined}
                hasSearch
                onQueryChange={vi.fn()}
                onRemoveDomain={vi.fn()}
                onClear={vi.fn()}
            />,
        );

        const label = screen.getByText(/^Domain: /);
        expect(label).toHaveClass('min-w-0', 'truncate');
        expect(label.parentElement).toHaveClass('max-w-full');
    });
});
