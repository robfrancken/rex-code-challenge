import { useCallback } from 'react';
import { useSearchParams, type NavigateOptions } from 'react-router';
import type { SearchParams } from '@/modules/contacts/api/contacts';

export const SEARCH_KEYS = ['q', 'email_domain', 'cursor'] as const;

/**
 * The contact search as it lives in the URL (`?q=&email_domain=&cursor=`): the API search params
 * it maps to, the raw values for the inputs, and the ways to change it.
 */
export function useContactSearchParams() {
    const [urlParams, setUrlParams] = useSearchParams();

    const update = useCallback(
        (change: (next: URLSearchParams) => void, options?: NavigateOptions) => {
            setUrlParams((previous) => {
                const next = new URLSearchParams(previous);
                change(next);

                return next;
            }, options);
        },
        [setUrlParams],
    );

    /** A changed search starts again from the first page, so the cursor is always dropped. */
    const setValue = useCallback(
        (key: 'q' | 'email_domain', value: string | null, options?: NavigateOptions) => {
            update((next) => {
                next.delete('cursor');
                if (value) {
                    next.set(key, value);
                } else {
                    next.delete(key);
                }
            }, options);
        },
        [update],
    );

    const setQuery = useCallback(
        (query: string) => setValue('q', query, { replace: true }),
        [setValue],
    );

    const setEmailDomain = useCallback(
        (domain: string | null) => setValue('email_domain', domain),
        [setValue],
    );

    const goToCursor = useCallback(
        (cursor: string) => update((next) => next.set('cursor', cursor)),
        [update],
    );

    const clear = useCallback(() => setUrlParams({}), [setUrlParams]);

    const params: SearchParams = {};
    for (const key of SEARCH_KEYS) {
        const value = urlParams.get(key);
        if (value) {
            params[key] = value;
        }
    }

    return {
        params,
        query: urlParams.get('q') ?? '',
        emailDomain: urlParams.get('email_domain') ?? '',
        hasSearch: SEARCH_KEYS.some((key) => urlParams.has(key) && key !== 'cursor'),
        setQuery,
        setEmailDomain,
        goToCursor,
        clear,
    };
}
