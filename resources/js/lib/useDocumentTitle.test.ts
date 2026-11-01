import { renderHook } from '@testing-library/react';
import { useDocumentTitle } from '@/lib/useDocumentTitle';

describe('useDocumentTitle', () => {
    beforeEach(() => {
        document.title = '';
    });

    it('sets the page title with the app name', () => {
        renderHook(() => useDocumentTitle('Contacts'));

        expect(document.title).toBe('Contacts · Rex CRM');
    });

    it('follows a changing page name', () => {
        const { rerender } = renderHook(({ page }) => useDocumentTitle(page), {
            initialProps: { page: 'Contact' },
        });

        rerender({ page: 'Chloe Smith' });

        expect(document.title).toBe('Chloe Smith · Rex CRM');
    });

    it('leaves the title alone when given no page name', () => {
        document.title = 'New contact · Rex CRM';

        renderHook(() => useDocumentTitle(undefined));

        expect(document.title).toBe('New contact · Rex CRM');
    });
});
