<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\DeleteContact;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;

it('deletes the contact and its children', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create();
    ContactPhoneFactory::new()->for($contact)->create();

    app(DeleteContact::class)->handle($contact->id);

    expect(Contact::query()->count())->toBe(0)
        ->and(ContactEmail::query()->count())->toBe(0)
        ->and(ContactPhone::query()->count())->toBe(0);
});

it('throws ContactNotFound when deleting twice', function (): void {
    $contact = ContactFactory::new()->create();
    app(DeleteContact::class)->handle($contact->id);

    app(DeleteContact::class)->handle($contact->id);
})->throws(ContactNotFound::class);
