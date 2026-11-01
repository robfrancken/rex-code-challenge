<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Database\UniqueConstraintViolationException;

it('loads emails and phones in insertion order', function (): void {
    $contact = ContactFactory::new()->create();
    $first = ContactEmailFactory::new()->for($contact)->create();
    $second = ContactEmailFactory::new()->for($contact)->create();
    $phone = ContactPhoneFactory::new()->for($contact)->create();

    $loaded = Contact::query()->with(['emails.contact', 'phones'])->findOrFail($contact->id);

    expect($loaded->emails->pluck('id')->all())->toBe([$first->id, $second->id])
        ->and($loaded->phones->pluck('id')->all())->toBe([$phone->id])
        ->and($loaded->emails->first()->contact->is($contact))->toBeTrue();
});

it('cascades deletes to child rows', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create();
    ContactPhoneFactory::new()->for($contact)->create();

    $contact->delete();

    expect(ContactEmail::query()->count())->toBe(0)
        ->and(ContactPhone::query()->count())->toBe(0);
});

it('rejects a duplicate email for the same contact', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create([
        'email' => 'a@b.com',
        'domain' => 'b.com',
    ]);

    ContactEmailFactory::new()->for($contact)->create([
        'email' => 'a@b.com',
        'domain' => 'b.com',
    ]);
})->throws(UniqueConstraintViolationException::class);
