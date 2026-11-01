<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Support\Str;

it('returns the contact with its relations loaded', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create();
    ContactPhoneFactory::new()->for($contact)->create();

    $found = app(GetContact::class)->handle($contact->id);

    expect($found->is($contact))->toBeTrue()
        ->and($found->relationLoaded('emails'))->toBeTrue()
        ->and($found->relationLoaded('phones'))->toBeTrue()
        ->and($found->emails)->toHaveCount(1)
        ->and($found->phones)->toHaveCount(1);
});

it('throws ContactNotFound for an unknown id', function (): void {
    try {
        app(GetContact::class)->handle((string) Str::uuid());
    } catch (ContactNotFound $exception) {
        expect($exception->problemCode())->toBe('contact_not_found')
            ->and($exception->status())->toBe(404);

        return;
    }

    $this->fail('Expected ContactNotFound.');
});
