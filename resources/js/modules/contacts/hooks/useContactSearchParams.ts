import { useCallback } from 'react';
import { useSearchParams, type NavigateOptions } from 'react-router';
import type { SearchParams } from '@/modules/contacts/api/contacts';

export const FILTER_KEYS = ['name', 'phone', 'email_domain'] as const;

export type FilterKey = (typeof FILTER_KEYS)[number];

export type Filters = Record<FilterKey, string>;

function toSearchParams(urlParams: URLSearchParams): SearchParams {
    const params: SearchParams = {};

    for (const key of FILTER_KEYS) {
        const value = urlParams.get(key);
        if (value) {
            params[key] = value;
        }
    }

    const page = Number(urlParams.get('page'));
    if (Number.isInteger(page) && page > 1) {
        params.page = page;
    }

    return params;
}

/**
 * The contact search as it lives in the URL (`?name=&phone=&email_domain=&page=`): the API
 * search params it maps to, the raw filter values for the inputs, and the ways to change it.
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

    /** Any filter change returns to page 1, so the user is never stranded past the last page. */
    const setFilter = useCallback(
        (key: FilterKey, value: string, options?: NavigateOptions) => {
            update((next) => {
                next.delete('page');
                if (value) {
                    next.set(key, value);
                } else {
                    next.delete(key);
                }
            }, options);
        },
        [update],
    );

    const goToPage = useCallback(
        (page: number, options?: NavigateOptions) => {
            update((next) => {
                if (page > 1) {
                    next.set('page', String(page));
                } else {
                    next.delete('page');
                }
            }, options);
        },
        [update],
    );

    const clear = useCallback(() => setUrlParams({}), [setUrlParams]);

    return {
        params: toSearchParams(urlParams),
        filters: {
            name: urlParams.get('name') ?? '',
            phone: urlParams.get('phone') ?? '',
            email_domain: urlParams.get('email_domain') ?? '',
        } satisfies Filters,
        hasFilters: FILTER_KEYS.some((key) => urlParams.has(key)),
        setFilter,
        goToPage,
        clear,
    };
}
