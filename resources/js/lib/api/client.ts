import axios, { AxiosError, type InternalAxiosRequestConfig } from 'axios';
import type { components } from '@/lib/api/schema';
import { ApiProblem } from '@/lib/api/problem';

type RetryableConfig = InternalAxiosRequestConfig & { _retried419?: boolean };

const PROBLEM_CONTENT_TYPE = 'application/problem+json';

export const api = axios.create({
    withCredentials: true,
    withXSRFToken: true,
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

function toApiProblem(error: AxiosError): ApiProblem | null {
    const response = error.response;
    const contentType = String(response?.headers?.['content-type'] ?? '');

    if (!response || !contentType.includes(PROBLEM_CONTENT_TYPE)) {
        return null;
    }

    const retryAfter = Number(response.headers['retry-after']);

    return new ApiProblem(
        response.data as components['schemas']['Problem'],
        Number.isFinite(retryAfter) && response.headers['retry-after'] !== undefined ? retryAfter : undefined,
    );
}

api.interceptors.response.use(undefined, (error: unknown) => {
    if (!(error instanceof AxiosError)) {
        return Promise.reject(error);
    }

    const config = error.config as RetryableConfig | undefined;

    if (error.response?.status === 419 && config && !config._retried419) {
        config._retried419 = true;

        return api.request(config);
    }

    if (error.response?.status === 401) {
        unauthenticatedHandler?.();
    }

    return Promise.reject(toApiProblem(error) ?? error);
});
