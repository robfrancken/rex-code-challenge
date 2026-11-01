<?php

declare(strict_types=1);

use Database\Factories\Contacts\ContactFactory;
use Illuminate\Support\Str;

it('shows a contact', function (): void {
    $contact = ContactFactory::new()->create(['name' => 'Jane Smith']);

    $this->artisan('contacts:show', ['id' => $contact->id])
        ->expectsOutputToContain('Jane Smith')
        ->assertExitCode(0);
});

it('fails for an unknown contact', function (): void {
    $this->artisan('contacts:show', ['id' => (string) Str::uuid()])
        ->expectsOutputToContain('No contact exists')
        ->assertExitCode(1);
});
