<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * @return array<string, mixed>
 */
function openApiDocument(): array
{
    /** @var array<string, mixed> */
    return json_decode(
        (string) file_get_contents(__DIR__.'/openapi.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('is valid json with openapi 3.1', function (): void {
    $document = openApiDocument();

    expect($document['openapi'])->toBe('3.1.0')
        ->and($document['info']['title'])->toBe('Rex CRM Contacts API')
        ->and($document['info']['version'])->toBe('1.0.0');
});

it('documents exactly the module and auth routes', function (): void {
    $actual = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/') || in_array($route->uri(), ['login', 'logout'], true))
        ->flatMap(fn ($route) => collect($route->methods())
            ->reject(fn (string $method): bool => $method === 'HEAD')
            ->map(fn (string $method): string => strtolower($method).' /'.$route->uri()))
        ->unique()
        ->sort()
        ->values()
        ->all();

    $documented = collect(openApiDocument()['paths'])
        ->flatMap(fn (array $operations, string $path) => collect(array_keys($operations))
            ->filter(fn (string $key): bool => in_array($key, ['get', 'put', 'post', 'delete', 'patch'], true))
            ->map(fn (string $method): string => $method.' '.$path))
        ->sort()
        ->values()
        ->all();

    expect($documented)->toBe($actual);
});

it('documents every problem code', function (): void {
    expect(openApiDocument()['components']['schemas']['Problem']['properties']['code']['enum'])->toBe([
        'validation_failed',
        'invalid_credentials',
        'contact_not_found',
        'not_found',
        'unauthenticated',
        'csrf_token_mismatch',
        'idempotency_key_missing',
        'idempotency_key_reused',
        'idempotency_key_invalid',
        'idempotency_request_in_progress',
        'telephony_failed',
        'too_many_requests',
        'http_error',
        'server_error',
    ]);
});
