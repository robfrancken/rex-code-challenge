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
    it('reads q, email_domain and cursor from the url into the API search params', () => {
        const { result } = renderSearchParams('/contacts?q=smi&email_domain=rex.com.au&cursor=abc');

        expect(result.current.searchParams.params).toEqual({
            q: 'smi',
            email_domain: 'rex.com.au',
            cursor: 'abc',
        });
        expect(result.current.searchParams.query).toBe('smi');
        expect(result.current.searchParams.emailDomain).toBe('rex.com.au');
        expect(result.current.searchParams.hasSearch).toBe(true);
    });

    it.each([['q='], ['page=2'], ['name=ch']])(
        'ignores an empty or unknown param (%s)',
        (query) => {
            const { result } = renderSearchParams(`/contacts?${query}`);

            expect(result.current.searchParams.params).toEqual({});
        },
    );

    it('a cursor alone is not a search', () => {
        const { result } = renderSearchParams('/contacts?cursor=abc');

        expect(result.current.searchParams.hasSearch).toBe(false);
    });

    it('setQuery replaces the history entry and drops the cursor', () => {
        const { result } = renderSearchParams('/contacts?cursor=abc');

        act(() => result.current.searchParams.setQuery('smi'));

        expect(result.current.location.search).toBe('?q=smi');
        expect(result.current.navigationType).toBe('REPLACE');
    });

    it('setQuery removes an emptied query', () => {
        const { result } = renderSearchParams('/contacts?q=smi&email_domain=x.com');

        act(() => result.current.searchParams.setQuery(''));

        expect(result.current.location.search).toBe('?email_domain=x.com');
    });

    it('setEmailDomain sets the domain, pushes a history entry and drops the cursor', () => {
        const { result } = renderSearchParams('/contacts?q=smi&cursor=abc');

        act(() => result.current.searchParams.setEmailDomain('rex.com.au'));

        expect(result.current.location.search).toBe('?q=smi&email_domain=rex.com.au');
        expect(result.current.navigationType).toBe('PUSH');
    });

    it('setEmailDomain(null) removes the chip parameter', () => {
        const { result } = renderSearchParams('/contacts?q=smi&email_domain=rex.com.au&cursor=abc');

        act(() => result.current.searchParams.setEmailDomain(null));

        expect(result.current.location.search).toBe('?q=smi');
    });

    it('goToCursor pushes a new history entry and keeps the search', () => {
        const { result } = renderSearchParams('/contacts?q=smi');

        act(() => result.current.searchParams.goToCursor('x'));

        expect(result.current.location.search).toBe('?q=smi&cursor=x');
        expect(result.current.navigationType).toBe('PUSH');
    });

    it('clear removes every param', () => {
        const { result } = renderSearchParams('/contacts?q=smi&email_domain=x.com&cursor=abc');

        act(() => result.current.searchParams.clear());

        expect(result.current.location.search).toBe('');
        expect(result.current.searchParams.hasSearch).toBe(false);
    });
});
