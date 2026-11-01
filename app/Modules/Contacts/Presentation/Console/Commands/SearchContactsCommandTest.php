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

it('searches by q and prints the match', function (): void {
    $this->artisan('contacts:search', ['q' => 'smi'])
        ->expectsOutputToContain('Jane Smith')
        ->assertExitCode(0);
})->group('fulltext');

it('searches by email domain', function (): void {
    $this->artisan('contacts:search', ['--domain' => 'rex.com.au'])
        ->expectsOutputToContain('Jane Smith')
        ->assertExitCode(0);
})->group('fulltext');

it('prints the next cursor when there are more results', function (): void {
    ContactFactory::new()->count(30)->create();

    $this->artisan('contacts:search')
        ->expectsOutputToContain('Next page: --cursor=')
        ->assertExitCode(0);
});

it('prints no next cursor on the last page', function (): void {
    Artisan::call('contacts:search');

    expect(Artisan::output())->not->toContain('Next page');
});

it('prints json with the matches and cursors', function (): void {
    Artisan::call('contacts:search', [
        '--domain' => 'rex.com.au',
        '--json' => true,
    ]);
    $decoded = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($decoded)->toHaveKeys(['data', 'next_cursor', 'prev_cursor'])
        ->and($decoded['next_cursor'])->toBeNull()
        ->and($decoded['data'])->toHaveCount(1)
        ->and($decoded['data'][0])->toHaveKeys(['id', 'name', 'emails', 'phones'])
        ->and($decoded['data'][0]['name'])->toBe('Jane Smith')
        ->and($decoded['data'][0]['emails'])->toBe(['jane@rex.com.au']);
})->group('fulltext');

it('follows --cursor to the next page', function (): void {
    ContactFactory::new()->count(30)->create();
    Artisan::call('contacts:search', [
        '--per-page' => 20,
        '--json' => true,
    ]);
    $first = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    Artisan::call('contacts:search', [
        '--per-page' => 20,
        '--cursor' => $first['next_cursor'],
        '--json' => true,
    ]);
    $second = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($second['data'])->toHaveCount(11)
        ->and($second['next_cursor'])->toBeNull();
});

it('rejects an invalid page size', function (): void {
    $this->artisan('contacts:search', ['--per-page' => '1000'])
        ->expectsOutputToContain('per_page')
        ->assertExitCode(2);
});

it('rejects a malformed cursor', function (): void {
    $this->artisan('contacts:search', ['--cursor' => 'garbage'])
        ->expectsOutputToContain('cursor')
        ->assertExitCode(2);
});
