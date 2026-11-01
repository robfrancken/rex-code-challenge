<?php

declare(strict_types=1);

use App\Exceptions\ProblemException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Route::middleware('web')->get('api/test/validation', function (): void {
        throw ValidationException::withMessages(['name' => 'The name is required.']);
    });
    Route::middleware('web')->get('api/test/problem', function (): void {
        throw new class('I am a teapot.') extends ProblemException
        {
            public function problemCode(): string
            {
                return 'teapot_test';
            }

            public function status(): int
            {
                return 418;
            }

            public function title(): string
            {
                return 'Teapot';
            }
        };
    });
    Route::middleware('web')->get('api/test/unauthenticated', function (): void {
        throw new AuthenticationException;
    });
    Route::middleware('web')->get('api/test/throttle', function (): void {
        throw new ThrottleRequestsException('Slow down.', null, ['Retry-After' => '30']);
    });
    Route::middleware('web')->get('api/test/boom', function (): void {
        throw new RuntimeException('SQLSTATE secret');
    });
});

it('renders validation errors as problem details', function (): void {
    $response = $this->getJson('api/test/validation');

    $response->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonPath('type', '/problems/validation_failed')
        ->assertJsonPath('status', 422)
        ->assertJsonStructure(['errors' => ['name']]);
    expect($response->headers->get('Content-Type'))->toContain('application/problem+json');
});

it('renders a ProblemException with its own code and status', function (): void {
    $this->getJson('api/test/problem')
        ->assertStatus(418)
        ->assertJsonPath('code', 'teapot_test')
        ->assertJsonPath('type', '/problems/teapot_test')
        ->assertJsonPath('title', 'Teapot')
        ->assertJsonPath('detail', 'I am a teapot.');
});

it('renders unauthenticated as 401', function (): void {
    $this->getJson('api/test/unauthenticated')
        ->assertStatus(401)
        ->assertJsonPath('code', 'unauthenticated');
});

it('renders throttling as 429 with Retry-After', function (): void {
    $this->getJson('api/test/throttle')
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After', '30');
});

it('renders an unknown route as 404 not_found', function (): void {
    $this->getJson('api/test/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('code', 'not_found');
});

it('hides internals of unexpected exceptions even in debug mode', function (): void {
    config(['app.debug' => true]);

    $response = $this->getJson('api/test/boom');

    $response->assertStatus(500)->assertJsonPath('code', 'server_error');
    expect($response->getContent())
        ->not->toContain('SQLSTATE')
        ->not->toContain('RuntimeException')
        ->not->toContain('trace');
});
