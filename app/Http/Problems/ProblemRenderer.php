<?php

declare(strict_types=1);

namespace App\Http\Problems;

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
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ProblemException => ProblemDetails::response(
                $e->status(),
                $e->problemCode(),
                $e->title(),
                $e->getMessage(),
                $e->extensions(),
                $e->headers(),
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
