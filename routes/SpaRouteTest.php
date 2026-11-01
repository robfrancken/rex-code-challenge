<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function (): void {
    $this->withoutVite();
});

it('serves the spa shell on a client side path', function (): void {
    $this->get('/contacts/abc')->assertOk()->assertSee('id="app"', false);
});

it('serves the spa shell at the root', function (): void {
    $this->get('/')->assertOk()->assertSee('id="app"', false);
});

it('keeps unknown api paths as json problems', function (): void {
    $this->actingAs(User::factory()->create());

    $this->getJson('/api/v1/unknown')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});

it('keeps the login endpoint', function (): void {
    $this->postJson('/login', [
        'email' => 'nobody@example.com',
        'password' => 'wrong',
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_credentials');
});

it('keeps the health endpoint out of the shell', function (): void {
    $this->get('/up')->assertOk()->assertDontSee('id="app"', false);
});

it('does not serve the shell for paths with a file extension', function (): void {
    $this->get('/missing.js')->assertNotFound();
});
