import { ApiProblem, isApiProblem, problemMessage } from '@/lib/api/problem';

describe('ApiProblem', () => {
    it('is built from a 422 problem body with field errors', () => {
        const problem = new ApiProblem({
            type: '/problems/validation_failed',
            title: 'Validation failed',
            status: 422,
            detail: 'The given data was invalid.',
            code: 'validation_failed',
            errors: { name: ['The name field is required.'] },
        });

        expect(problem.status).toBe(422);
        expect(problem.code).toBe('validation_failed');
        expect(problem.title).toBe('Validation failed');
        expect(problem.detail).toBe('The given data was invalid.');
        expect(problem.errors).toEqual({ name: ['The name field is required.'] });
        expect(problem.retryAfter).toBeUndefined();
    });

    it('defaults errors to an empty object and keeps retryAfter', () => {
        const problem = new ApiProblem(
            {
                type: '/problems/too_many_requests',
                title: 'Slow down',
                status: 429,
                detail: 'Try later.',
                code: 'too_many_requests',
            },
            30,
        );

        expect(problem.errors).toEqual({});
        expect(problem.retryAfter).toBe(30);
    });

    it('is recognised by isApiProblem but a plain Error is not', () => {
        const problem = new ApiProblem({
            type: 't',
            title: 'x',
            status: 404,
            detail: 'y',
            code: 'not_found',
        });

        expect(isApiProblem(problem)).toBe(true);
        expect(isApiProblem(new Error('boom'))).toBe(false);
        expect(isApiProblem(undefined)).toBe(false);
    });
});

describe('problemMessage', () => {
    it('uses the problem title and detail', () => {
        const problem = new ApiProblem({
            type: 't',
            title: 'Nope',
            status: 500,
            detail: 'Broke.',
            code: 'server_error',
        });

        expect(problemMessage(problem)).toEqual({ title: 'Nope', detail: 'Broke.' });
    });

    it('falls back for anything else without leaking the raw message', () => {
        expect(problemMessage(new Error('Network Error'), 'Could not save')).toEqual({
            title: 'Could not save',
            detail: 'Please try again.',
        });
    });
});
