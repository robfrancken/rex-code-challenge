<?php

declare(strict_types=1);

namespace App\Http\Problems;

use App\Exceptions\ApplicationException;
use App\Exceptions\ProblemException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class ProblemRenderer
{
    /**
     * HTTP status and title for each transport-neutral application exception, registered by the modules' Presentation layers.
     *
     * @var array<class-string<ApplicationException>, array{status: int, title: string}>
     */
    private static array $applicationProblems = [];

    /**
     * Maps an application exception (and its subclasses) to an HTTP status and problem title.
     *
     * @param  class-string<ApplicationException>  $exceptionClass
     */
    public static function map(
        string $exceptionClass,
        int $status,
        string $title,
    ): void {
        self::$applicationProblems[$exceptionClass] = [
            'status' => $status,
            'title' => $title,
        ];
    }

    public static function render(Throwable $e): JsonResponse
    {
        $applicationProblem = $e instanceof ApplicationException ? self::applicationProblem($e) : null;

        return match (true) {
            $e instanceof ProblemException => ProblemDetails::response(
                $e->status(),
                $e->problemCode(),
                $e->title(),
                $e->getMessage(),
                $e->extensions(),
                $e->headers(),
            ),
            $e instanceof ApplicationException && $applicationProblem !== null => ProblemDetails::response(
                $applicationProblem['status'],
                $e->problemCode(),
                $applicationProblem['title'],
                $e->getMessage(),
            ),
            $e instanceof ValidationException => ProblemDetails::response(
                422,
                'validation_failed',
                'Validation failed',
                'The given data was invalid.',
                ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => ProblemDetails::response(
                401,
                'unauthenticated',
                'Unauthenticated',
                'Authentication is required to access this resource.',
            ),
            $e instanceof TokenMismatchException,
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 419 => ProblemDetails::response(
                419,
                'csrf_token_mismatch',
                'CSRF token mismatch',
                'The CSRF token is missing or has expired.',
            ),
            $e instanceof ThrottleRequestsException => ProblemDetails::response(
                429,
                'too_many_requests',
                'Too many requests',
                'Too many requests. Please slow down.',
                [],
                self::stringHeaders($e->getHeaders()),
            ),
            $e instanceof NotFoundHttpException,
            $e instanceof ModelNotFoundException => ProblemDetails::response(
                404,
                'not_found',
                'Not found',
                'The requested resource was not found.',
            ),
            $e instanceof HttpExceptionInterface => ProblemDetails::response(
                $e->getStatusCode(),
                'http_error',
                'HTTP error',
                'The request could not be processed.',
                [],
                self::stringHeaders($e->getHeaders()),
            ),
            default => ProblemDetails::response(
                500,
                'server_error',
                'Server error',
                'An unexpected error occurred.',
            ),
        };
    }

    /**
     * An unmapped application exception returns null and so renders as a 500 that hides its message.
     *
     * @return array{status: int, title: string}|null
     */
    private static function applicationProblem(ApplicationException $e): ?array
    {
        foreach (self::$applicationProblems as $exceptionClass => $problem) {
            if ($e instanceof $exceptionClass) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $headers
     * @return array<string, string>
     */
    private static function stringHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $result[$name] = (string) $value;
            }
        }

        return $result;
    }
}
