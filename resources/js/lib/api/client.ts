import axios, { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';
import type { components } from '@/lib/api/schema';
import { ApiProblem } from '@/lib/api/problem';

type RetryableConfig = InternalAxiosRequestConfig & { _retried419?: boolean };

const PROBLEM_CONTENT_TYPE = 'application/problem+json';

/**
 * The one HTTP client for the same-origin API. Axios copies the XSRF-TOKEN cookie into the
 * X-XSRF-TOKEN header for same-origin requests by default, so `withXSRFToken` stays unset: `true`
 * would send the token to any origin. `allowAbsoluteUrls: false` keeps every request on the base
 * URL, so a full URL can never take the session cookie or token elsewhere.
 */
export const api = axios.create({
    baseURL: '/',
    allowAbsoluteUrls: false,
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

let unauthenticatedHandler: (() => void) | null = null;

/** Registers the single handler run on a 401; returns a function that unregisters it. */
export function onUnauthenticated(handler: () => void): () => void {
    unauthenticatedHandler = handler;

    return () => {
        if (unauthenticatedHandler === handler) {
            unauthenticatedHandler = null;
        }
    };
}

function parseRetryAfter(value: unknown): number | undefined {
    const text = String(value ?? '').trim();
    const seconds = Number(text);

    return text !== '' && Number.isFinite(seconds) ? seconds : undefined;
}

function toApiProblem(error: AxiosError): ApiProblem | null {
    const response = error.response;
    const contentType = String(response?.headers?.['content-type'] ?? '');

    if (!response || !contentType.includes(PROBLEM_CONTENT_TYPE)) {
        return null;
    }

    const retryAfter = parseRetryAfter(response.headers['retry-after']);

    if (typeof response.data !== 'object' || response.data === null) {
        return new ApiProblem(
            {
                type: '/problems/server_error',
                title: 'Unexpected response',
                status: response.status,
                detail: 'The server sent an unreadable error response.',
                code: 'server_error',
            },
            retryAfter,
        );
    }

    return new ApiProblem(response.data as components['schemas']['Problem'], retryAfter);
}

/**
 * Laravel only issues a fresh XSRF-TOKEN cookie on requests that pass the CSRF check, so a 419 leaves the stale
 * cookie in place. Any web GET passes, which makes it a cheap way to get a new token.
 */
async function refreshCsrfCookie(): Promise<void> {
    const config: Partial<RetryableConfig> = {
        headers: new AxiosHeaders({ Accept: 'text/html' }),
        _retried419: true,
    };

    await api.get('/', config);
}

api.interceptors.response.use(undefined, async (error: unknown) => {
    if (!(error instanceof AxiosError)) {
        return Promise.reject(error);
    }

    const config = error.config as RetryableConfig | undefined;

    if (error.response?.status === 419 && config && !config._retried419) {
        config._retried419 = true;

        try {
            await refreshCsrfCookie();
        } catch {
            return Promise.reject(toApiProblem(error) ?? error);
        }

        return api.request(config);
    }

    if (error.response?.status === 401) {
        unauthenticatedHandler?.();
    }

    return Promise.reject(toApiProblem(error) ?? error);
});
