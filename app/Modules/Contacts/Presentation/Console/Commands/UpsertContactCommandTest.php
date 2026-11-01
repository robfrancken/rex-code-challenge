<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

it('creates then updates a contact', function (): void {
    $id = (string) Str::uuid();
    $arguments = [
        'id' => $id,
        '--name' => 'Jane Smith',
        '--email' => ['jane@rex.com.au'],
        '--phone' => ['+61412345678'],
    ];

    $this->artisan('contacts:upsert', $arguments)
        ->expectsOutputToContain('Created contact '.$id)
        ->assertExitCode(Command::SUCCESS);

    $this->artisan('contacts:upsert', $arguments)
        ->expectsOutputToContain('Updated contact '.$id)
        ->assertExitCode(Command::SUCCESS);

    expect(Contact::query()->count())->toBe(1);
});

it('generates an id when none is given', function (): void {
    $this->artisan('contacts:upsert', [
        '--name' => 'Jane Smith',
        '--email' => ['jane@rex.com.au'],
        '--phone' => ['+61412345678'],
    ])
        ->expectsOutputToContain('Created contact')
        ->assertExitCode(Command::SUCCESS);
});

it('reports validation errors as field: message lines', function (): void {
    $this->artisan('contacts:upsert', [
        '--name' => 'Jane Smith',
        '--phone' => ['0412345678'],
    ])
        ->expectsOutputToContain('phones.0')
        ->assertExitCode(Command::INVALID);

    expect(Contact::query()->count())->toBe(0);
});

it('prints the contact as json', function (): void {
    $this->artisan('contacts:upsert', [
        '--name' => 'Jane Smith',
        '--email' => ['jane@rex.com.au'],
        '--phone' => ['+61412345678'],
        '--json' => true,
    ])
        ->expectsOutputToContain('"name":"Jane Smith"')
        ->assertExitCode(Command::SUCCESS);
});
