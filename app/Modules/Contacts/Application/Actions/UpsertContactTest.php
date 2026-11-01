<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\UpsertContact;
use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @param  list<string>  $emails
 * @param  list<string>  $phones
 */
function upsertData(string $id, string $name = 'Jane', array $emails = ['jane@example.com'], array $phones = ['+61412345678']): UpsertContactData
{
    return UpsertContactData::fromValidated(['id' => $id, 'name' => $name, 'emails' => $emails, 'phones' => $phones]);
}

it('creates a contact with emails and phones', function (): void {
    $id = (string) Str::uuid();

    $result = app(UpsertContact::class)->handle(upsertData($id, emails: ['a@x.com', 'b@y.org']));

    expect($result->created)->toBeTrue()
        ->and(Contact::query()->count())->toBe(1)
        ->and(ContactEmail::query()->count())->toBe(2)
        ->and(ContactEmail::query()->orderBy('id')->pluck('domain')->all())->toBe(['x.com', 'y.org'])
        ->and(ContactPhone::query()->count())->toBe(1)
        ->and($result->contact->relationLoaded('emails'))->toBeTrue()
        ->and($result->contact->relationLoaded('phones'))->toBeTrue();
});

it('is idempotent', function (): void {
    $id = (string) Str::uuid();
    $data = upsertData($id, emails: ['a@x.com', 'b@y.org']);

    app(UpsertContact::class)->handle($data);
    $emailIds = ContactEmail::query()->orderBy('id')->pluck('id')->all();
    $phoneIds = ContactPhone::query()->orderBy('id')->pluck('id')->all();

    $second = app(UpsertContact::class)->handle($data);

    expect($second->created)->toBeFalse()
        ->and(ContactEmail::query()->orderBy('id')->pluck('id')->all())->toBe($emailIds)
        ->and(ContactPhone::query()->orderBy('id')->pluck('id')->all())->toBe($phoneIds)
        ->and(Contact::query()->count())->toBe(1);
});

it('updates the name and syncs children', function (): void {
    $id = (string) Str::uuid();
    app(UpsertContact::class)->handle(upsertData($id, emails: ['keep@x.com', 'drop@x.com']));
    $keptId = ContactEmail::query()->where('email', 'keep@x.com')->value('id');

    $result = app(UpsertContact::class)->handle(upsertData($id, 'Janet', ['keep@x.com', 'new@x.com'], ['+64211234567']));

    expect($result->contact->name)->toBe('Janet')
        ->and(ContactEmail::query()->orderBy('email')->pluck('email')->all())->toBe(['keep@x.com', 'new@x.com'])
        ->and(ContactEmail::query()->where('email', 'keep@x.com')->value('id'))->toBe($keptId)
        ->and(ContactPhone::query()->pluck('phone')->all())->toBe(['+64211234567']);
});

it('de-duplicates values that normalise to the same email', function (): void {
    $id = (string) Str::uuid();

    app(UpsertContact::class)->handle(upsertData($id, emails: ['A@x.com', ' a@x.com']));
    $rowId = ContactEmail::query()->value('id');

    app(UpsertContact::class)->handle(upsertData($id, emails: ['a@X.COM']));

    expect(ContactEmail::query()->count())->toBe(1)
        ->and(ContactEmail::query()->value('id'))->toBe($rowId);
});

it('handles a concurrent first create', function (): void {
    $id = (string) Str::uuid();
    DB::table('contacts')->insert(['id' => $id, 'name' => 'Raced', 'created_at' => now(), 'updated_at' => now()]);

    $result = app(UpsertContact::class)->handle(upsertData($id, 'Jane'));

    expect($result->created)->toBeFalse()
        ->and($result->contact->name)->toBe('Jane')
        ->and(ContactEmail::query()->count())->toBe(1);
});
