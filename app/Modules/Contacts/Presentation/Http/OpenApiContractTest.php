<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    /** @var array<string, mixed> $document */
    $document = json_decode(
        (string) file_get_contents(__DIR__.'/openapi.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $this->document = $document;
});

it('is valid json with openapi 3.1', function (): void {
    $document = $this->document;

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

    $documented = collect($this->document['paths'])
        ->flatMap(fn (array $operations, string $path) => collect(array_keys($operations))
            ->filter(fn (string $key): bool => in_array($key, ['get', 'put', 'post', 'delete', 'patch'], true))
            ->map(fn (string $method): string => $method.' '.$path))
        ->sort()
        ->values()
        ->all();

    expect($documented)->toBe($actual);
});

it('documents every problem code', function (): void {
    expect($this->document['components']['schemas']['Problem']['properties']['code']['enum'])->toBe([
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

it('resolves every $ref', function (): void {
    $unresolved = [];
    $walk = function (mixed $node) use (&$walk, &$unresolved): void {
        if (! is_array($node)) {
            return;
        }
        foreach ($node as $key => $value) {
            if ($key === '$ref' && is_string($value) && str_starts_with($value, '#/')) {
                $target = $this->document;
                foreach (explode('/', substr($value, 2)) as $segment) {
                    $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
                    if (! is_array($target) || ! array_key_exists($segment, $target)) {
                        $unresolved[] = $value;
                        break;
                    }
                    $target = $target[$segment];
                }
            } else {
                $walk($value);
            }
        }
    };
    $walk($this->document);

    expect($unresolved)->toBe([]);
});
