<?php

declare(strict_types=1);

use Database\Factories\Contacts\ContactFactory;

it('deletes a contact and then fails because it is gone', function (): void {
    $contact = ContactFactory::new()->create();

    $this->artisan('contacts:delete', ['id' => $contact->id])
        ->expectsOutputToContain('Deleted contact '.$contact->id)
        ->assertExitCode(0);

    $this->artisan('contacts:delete', ['id' => $contact->id])->assertExitCode(1);
});
