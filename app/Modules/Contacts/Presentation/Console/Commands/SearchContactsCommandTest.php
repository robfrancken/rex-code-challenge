<?php

declare(strict_types=1);

use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    $contact = ContactFactory::new()->create(['name' => 'Jane Smith']);
    ContactEmailFactory::new()->for($contact)->create([
        'email' => 'jane@rex.com.au',
        'domain' => 'rex.com.au',
    ]);
});

it('searches by email domain and prints the page summary', function (): void {
    $this->artisan('contacts:search', ['--domain' => 'rex.com.au'])
        ->expectsOutputToContain('Jane Smith')
        ->expectsOutputToContain('Page 1 of 1 (1 contacts)')
        ->assertExitCode(0);
});

it('prints json that decodes to the matches', function (): void {
    Artisan::call('contacts:search', [
        '--domain' => 'rex.com.au',
        '--json' => true,
    ]);
    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toHaveCount(1)
        ->and($decoded[0])->toHaveKeys(['id', 'name', 'emails', 'phones'])
        ->and($decoded[0]['name'])->toBe('Jane Smith')
        ->and($decoded[0]['emails'])->toBe(['jane@rex.com.au']);
});

it('rejects an invalid page size', function (): void {
    $this->artisan('contacts:search', ['--per-page' => '1000'])
        ->expectsOutputToContain('per_page')
        ->assertExitCode(2);
});
