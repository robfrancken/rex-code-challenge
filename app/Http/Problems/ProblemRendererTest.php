<?php

declare(strict_types=1);

use App\Exceptions\ApplicationException;
use App\Exceptions\ProblemException;
use App\Http\Problems\ProblemRenderer;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $mapped = new class('The widget is missing.') extends ApplicationException
    {
        public function problemCode(): string
        {
            return 'widget_missing';
        }
    };
    ProblemRenderer::map($mapped::class, 404, 'Widget missing');
    Route::middleware('web')->get('api/test/application', fn () => throw $mapped);
    Route::middleware('web')->get('api/test/unmapped', function (): void {
        throw new class('Gadget +61412345678 is broken.') extends ApplicationException
        {
            public function problemCode(): string
            {
                return 'gadget_broken';
            }
        };
    });
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

it('renders a mapped application exception with the registered status and title', function (): void {
    $this->getJson('api/test/application')
        ->assertNotFound()
        ->assertExactJson([
            'type' => '/problems/widget_missing',
            'title' => 'Widget missing',
            'status' => 404,
            'detail' => 'The widget is missing.',
            'code' => 'widget_missing',
        ]);
});

it('renders an unmapped application exception as 500 server_error without its message', function (): void {
    $response = $this->getJson('api/test/unmapped');

    $response->assertInternalServerError()->assertJsonPath('code', 'server_error');
    expect($response->getContent())
        ->not->toContain('gadget')
        ->not->toContain('+61412345678');
});

it('does not report problem or application exceptions', function (string $uri): void {
    Log::spy();

    $this->getJson($uri);

    Log::shouldNotHaveReceived('error');
})->with([
    'problem exception' => 'api/test/problem',
    'application exception' => 'api/test/application',
]);

it('still reports unexpected exceptions at error level', function (): void {
    Log::spy();

    $this->getJson('api/test/boom');

    Log::shouldHaveReceived('error')->once();
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
