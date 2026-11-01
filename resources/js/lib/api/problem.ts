import type { components } from '@/lib/api/schema';

type Problem = components['schemas']['Problem'];

export type ProblemCode = Problem['code'];

/** An RFC 9457 problem response from the API, as a throwable error. */
export class ApiProblem extends Error {
    readonly status: number;
    readonly code: ProblemCode;
    readonly title: string;
    readonly detail: string;
    readonly errors: Record<string, string[]>;
    readonly retryAfter?: number;

    constructor(problem: Problem, retryAfter?: number) {
        super(problem.detail);
        this.name = 'ApiProblem';
        this.status = problem.status;
        this.code = problem.code;
        this.title = problem.title;
        this.detail = problem.detail;
        this.errors = problem.errors ?? {};
        this.retryAfter = retryAfter;
    }
}

export function isApiProblem(e: unknown): e is ApiProblem {
    return e instanceof ApiProblem;
}

/** The title and detail to show for any thrown error; raw error messages are never exposed. */
export function problemMessage(
    error: unknown,
    fallbackTitle = 'Something went wrong',
    fallbackDetail = 'Please try again.',
): { title: string; detail: string } {
    return isApiProblem(error)
        ? { title: error.title, detail: error.detail }
        : { title: fallbackTitle, detail: fallbackDetail };
}
