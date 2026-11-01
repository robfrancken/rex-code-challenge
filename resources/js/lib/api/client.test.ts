import {
    AxiosError,
    type AxiosAdapter,
    type AxiosResponse,
    type InternalAxiosRequestConfig,
} from 'axios';
import { api, onUnauthenticated } from '@/lib/api/client';
import { isApiProblem } from '@/lib/api/problem';

type Reply = {
    status: number;
    data?: unknown;
    problem?: boolean;
    headers?: Record<string, string>;
};

const originalAdapter = api.defaults.adapter;

function respondWith(...replies: Reply[]): { calls: () => number } {
    let call = 0;
    const adapter: AxiosAdapter = (config: InternalAxiosRequestConfig) => {
        const reply = replies[Math.min(call, replies.length - 1)];
        call += 1;
        const response = {
            status: reply.status,
            statusText: '',
            data: reply.data ?? {},
            headers: {
                'content-type': reply.problem ? 'application/problem+json' : 'application/json',
                ...reply.headers,
            },
            config,
        } as AxiosResponse;

        return reply.status < 400
            ? Promise.resolve(response)
            : Promise.reject(
                  new AxiosError('failed', String(reply.status), config, null, response),
              );
    };
    api.defaults.adapter = adapter;

    return { calls: () => call };
}

function problemBody(status: number, code: string) {
    return {
        type: `/problems/${code}`,
        title: `Title ${code}`,
        status,
        detail: `Detail ${code}`,
        code,
    };
}

afterEach(() => {
    api.defaults.adapter = originalAdapter;
});

describe('api client', () => {
    it('sends the global defaults', async () => {
        let sent: InternalAxiosRequestConfig | undefined;
        api.defaults.adapter = (config) => {
            sent = config;
            return Promise.resolve({ status: 200, statusText: '', data: {}, headers: {}, config });
        };

        await api.get('/x');

        expect(sent?.withCredentials).toBe(true);
        expect(sent?.withXSRFToken).toBe(true);
        expect(sent?.headers.get('Accept')).toBe('application/json');
        expect(sent?.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
    });

    it('rejects a problem+json response with an ApiProblem carrying its code', async () => {
        respondWith({ status: 404, problem: true, data: problemBody(404, 'contact_not_found') });

        const error = await api.get('/api/v1/contacts/x').catch((e: unknown) => e);

        expect(isApiProblem(error)).toBe(true);
        expect(error).toMatchObject({
            status: 404,
            code: 'contact_not_found',
            title: 'Title contact_not_found',
        });
    });

    it('reads Retry-After into the problem', async () => {
        respondWith({
            status: 429,
            problem: true,
            data: problemBody(429, 'too_many_requests'),
            headers: { 'retry-after': '12' },
        });

        const error = await api.get('/x').catch((e: unknown) => e);

        expect(error).toMatchObject({ code: 'too_many_requests', retryAfter: 12 });
    });

    it('retries a 419 once and resolves when the retry succeeds', async () => {
        const mock = respondWith(
            { status: 419, problem: true, data: problemBody(419, 'csrf_token_mismatch') },
            { status: 200 },
            { status: 200, data: { ok: true } },
        );

        const response = await api.post('/api/v1/contacts', { name: 'x' });

        expect(response.data).toEqual({ ok: true });
        expect(mock.calls()).toBe(3);
    });

    it('rejects with an ApiProblem when the retry is also a 419', async () => {
        const mock = respondWith(
            { status: 419, problem: true, data: problemBody(419, 'csrf_token_mismatch') },
            { status: 200 },
            { status: 419, problem: true, data: problemBody(419, 'csrf_token_mismatch') },
        );

        const error = await api.post('/api/v1/contacts', {}).catch((e: unknown) => e);

        expect(isApiProblem(error)).toBe(true);
        expect(error).toMatchObject({ status: 419 });
        expect(mock.calls()).toBe(3);
    });

    it('calls the registered handler on a 401 and still rejects', async () => {
        const handler = vi.fn();
        const unsubscribe = onUnauthenticated(handler);
        respondWith({ status: 401, problem: true, data: problemBody(401, 'unauthenticated') });

        const error = await api.get('/api/v1/me').catch((e: unknown) => e);

        expect(handler).toHaveBeenCalledOnce();
        expect(error).toMatchObject({ status: 401, code: 'unauthenticated' });

        unsubscribe();
        await api.get('/api/v1/me').catch(() => undefined);
        expect(handler).toHaveBeenCalledOnce();
    });

    it('refreshes the csrf cookie with GET / before retrying a 419', async () => {
        const seen: string[] = [];
        let post = 0;
        api.defaults.adapter = (config) => {
            seen.push(`${config.method?.toUpperCase()} ${config.url}`);
            const response = (status: number, problem: boolean, data: unknown) =>
                ({
                    status,
                    statusText: '',
                    data,
                    headers: { 'content-type': problem ? 'application/problem+json' : 'text/html' },
                    config,
                }) as AxiosResponse;
            if (config.method === 'get') {
                return Promise.resolve(response(200, false, '<html>'));
            }
            post += 1;
            return post === 1
                ? Promise.reject(
                      new AxiosError(
                          'x',
                          '419',
                          config,
                          null,
                          response(419, true, problemBody(419, 'csrf_token_mismatch')),
                      ),
                  )
                : Promise.resolve(response(200, false, {}));
        };

        await api.post('/api/v1/contacts', {});

        expect(seen).toEqual(['POST /api/v1/contacts', 'GET /', 'POST /api/v1/contacts']);
    });

    it('rejects with the original 419 problem when the refresh fails', async () => {
        api.defaults.adapter = (config) => {
            const fail = (status: number, data: unknown) =>
                Promise.reject(
                    new AxiosError('x', String(status), config, null, {
                        status,
                        statusText: '',
                        data,
                        headers: { 'content-type': 'application/problem+json' },
                        config,
                    } as AxiosResponse),
                );
            return config.method === 'get'
                ? fail(500, problemBody(500, 'server_error'))
                : fail(419, problemBody(419, 'csrf_token_mismatch'));
        };

        const error = await api.post('/api/v1/contacts', {}).catch((e: unknown) => e);

        expect(error).toMatchObject({ status: 419, code: 'csrf_token_mismatch' });
    });

    it('ignores an empty or non-numeric Retry-After', async () => {
        respondWith({
            status: 429,
            problem: true,
            data: problemBody(429, 'too_many_requests'),
            headers: { 'retry-after': '' },
        });
        expect(await api.get('/x').catch((e: unknown) => e)).toMatchObject({
            retryAfter: undefined,
        });

        respondWith({
            status: 429,
            problem: true,
            data: problemBody(429, 'too_many_requests'),
            headers: { 'retry-after': 'soon' },
        });
        expect(await api.get('/x').catch((e: unknown) => e)).toMatchObject({
            retryAfter: undefined,
        });
    });

    it('rejects a malformed problem body with a generic ApiProblem', async () => {
        respondWith({ status: 502, problem: true, data: 'not an object' });

        const error = await api.get('/x').catch((e: unknown) => e);

        expect(isApiProblem(error)).toBe(true);
        expect(error).toMatchObject({
            status: 502,
            code: 'server_error',
            title: 'Unexpected response',
        });
    });

    it('passes non-problem errors through untouched', async () => {
        respondWith({ status: 502, data: 'bad gateway' });

        const error = await api.get('/x').catch((e: unknown) => e);

        expect(isApiProblem(error)).toBe(false);
        expect(error).toBeInstanceOf(AxiosError);
    });
});
