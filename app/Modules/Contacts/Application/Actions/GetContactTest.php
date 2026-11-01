<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Support\Facades\DB;
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
    expect(fn () => app(GetContact::class)->handle((string) Str::uuid()))
        ->toThrow(fn (ContactNotFound $exception) => expect($exception->problemCode())->toBe('contact_not_found'));
});

it('treats a non-uuid id as not found without querying', function (): void {
    DB::enableQueryLog();

    expect(fn () => app(GetContact::class)->handle('abc'))->toThrow(ContactNotFound::class);

    expect(DB::getQueryLog())->toBe([]);
});
