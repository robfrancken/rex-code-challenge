<?php

declare(strict_types=1);

use App\Models\User;

it('logs in with correct credentials', function (): void {
    $user = User::factory()->create(['email' => 'demo@example.com']);

    $this->postJson(route('login'), ['email' => 'demo@example.com', 'password' => 'password'])
        ->assertOk()
        ->assertExactJson(['id' => $user->id, 'name' => $user->name, 'email' => 'demo@example.com']);

    $this->assertAuthenticated();
});

it('gives the same response for a wrong password and an unknown email', function (): void {
    User::factory()->create(['email' => 'demo@example.com']);

    $wrongPassword = $this->postJson(route('login'), ['email' => 'demo@example.com', 'password' => 'nope']);
    $unknownEmail = $this->postJson(route('login'), ['email' => 'ghost@example.com', 'password' => 'password']);

    $wrongPassword->assertStatus(422)->assertJsonPath('code', 'invalid_credentials');
    expect($unknownEmail->getStatusCode())->toBe($wrongPassword->getStatusCode())
        ->and($unknownEmail->json())->toBe($wrongPassword->json());
    $this->assertGuest();
});

it('validates the login payload', function (): void {
    $this->postJson(route('login'), [])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');
});

it('rejects guests from the me endpoint', function (): void {
    $this->getJson(route('me'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('returns the authenticated user from the me endpoint', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson(route('me'))
        ->assertOk()
        ->assertExactJson(['id' => $user->id, 'name' => $user->name, 'email' => $user->email]);
});

it('logs out', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('logout'))
        ->assertNoContent();

    $this->assertGuest();
});

it('throttles the sixth login attempt within a minute', function (): void {
    $payload = ['email' => 'demo@example.com', 'password' => 'wrong'];

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('login'), $payload)->assertStatus(422);
    }

    $this->postJson(route('login'), $payload)
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests');
});

it('returns a validation problem, not a server error, for an array email', function (): void {
    $this->postJson(route('login'), ['email' => ['x'], 'password' => 'password'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed');
});

it('redirects a guest on a non-JSON request to the login route without erroring', function (): void {
    $this->get('/api/v1/me', ['Accept' => 'text/html'])->assertUnauthorized();
});
