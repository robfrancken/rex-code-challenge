import { ApiProblem } from '@/lib/api/problem';
import { createQueryClient } from '@/lib/query-client';

const problem = (status: number) =>
    new ApiProblem({ type: 't', title: 'x', status, detail: 'y', code: 'http_error' });

describe('createQueryClient', () => {
    it('uses a 30 second stale time and never retries mutations', () => {
        const defaults = createQueryClient().getDefaultOptions();

        expect(defaults.queries?.staleTime).toBe(30_000);
        expect(defaults.mutations?.retry).toBe(false);
    });

    it('does not retry client-error problems', () => {
        const retry = createQueryClient().getDefaultOptions().queries?.retry as (
            count: number,
            error: Error,
        ) => boolean;

        expect(retry(0, problem(404))).toBe(false);
        expect(retry(0, problem(422))).toBe(false);
    });

    it('retries server errors and unknown errors up to twice', () => {
        const retry = createQueryClient().getDefaultOptions().queries?.retry as (
            count: number,
            error: Error,
        ) => boolean;

        expect(retry(0, problem(500))).toBe(true);
        expect(retry(1, new Error('network'))).toBe(true);
        expect(retry(2, problem(500))).toBe(false);
    });
});
