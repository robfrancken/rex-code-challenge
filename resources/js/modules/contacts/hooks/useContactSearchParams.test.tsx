import { act, renderHook } from '@testing-library/react';
import type { ReactNode } from 'react';
import { MemoryRouter, useLocation, useNavigationType } from 'react-router';
import { useContactSearchParams } from '@/modules/contacts/hooks/useContactSearchParams';

function renderSearchParams(initialEntry: string) {
    return renderHook(
        () => ({
            searchParams: useContactSearchParams(),
            location: useLocation(),
            navigationType: useNavigationType(),
        }),
        {
            wrapper: ({ children }: { children: ReactNode }) => (
                <MemoryRouter initialEntries={[initialEntry]}>{children}</MemoryRouter>
            ),
        },
    );
}

describe('useContactSearchParams', () => {
    it('reads the filters and page from the url into the API search params', () => {
        const { result } = renderSearchParams(
            '/contacts?name=ch&phone=0412&email_domain=rex.com.au&page=3',
        );

        expect(result.current.searchParams.params).toEqual({
            name: 'ch',
            phone: '0412',
            email_domain: 'rex.com.au',
            page: 3,
        });
        expect(result.current.searchParams.filters).toEqual({
            name: 'ch',
            phone: '0412',
            email_domain: 'rex.com.au',
        });
        expect(result.current.searchParams.hasFilters).toBe(true);
    });

    it.each([['page=1'], ['page=0'], ['page=abc'], ['page=2.5'], ['name=']])(
        'leaves out an empty filter or a page that is not above 1 (%s)',
        (query) => {
            const { result } = renderSearchParams(`/contacts?${query}`);

            expect(result.current.searchParams.params).toEqual({});
            expect(result.current.searchParams.hasFilters).toBe(query === 'name=');
        },
    );

    it('setFilter sets the filter and returns to page 1', () => {
        const { result } = renderSearchParams('/contacts?name=ch&page=3');

        act(() => result.current.searchParams.setFilter('email_domain', 'rex.com.au'));

        expect(result.current.location.search).toBe('?name=ch&email_domain=rex.com.au');
        expect(result.current.navigationType).toBe('PUSH');
    });

    it('setFilter removes an emptied filter and can replace the history entry', () => {
        const { result } = renderSearchParams('/contacts?name=ch&phone=04');

        act(() => result.current.searchParams.setFilter('name', '', { replace: true }));

        expect(result.current.location.search).toBe('?phone=04');
        expect(result.current.navigationType).toBe('REPLACE');
    });

    it('goToPage sets the page and drops it for page 1', () => {
        const { result } = renderSearchParams('/contacts?name=ch');

        act(() => result.current.searchParams.goToPage(4));
        expect(result.current.location.search).toBe('?name=ch&page=4');

        act(() => result.current.searchParams.goToPage(1));
        expect(result.current.location.search).toBe('?name=ch');
    });

    it('clear removes every filter and the page', () => {
        const { result } = renderSearchParams(
            '/contacts?name=ch&phone=04&email_domain=x.com&page=2',
        );

        act(() => result.current.searchParams.clear());

        expect(result.current.location.search).toBe('');
        expect(result.current.searchParams.hasFilters).toBe(false);
    });
});
