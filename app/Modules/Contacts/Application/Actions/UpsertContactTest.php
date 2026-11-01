<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Application\Actions\UpsertContact;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    $updatedAt = DB::table('contacts')->where('id', $id)->value('updated_at');
    $this->travel(5)->minutes();
    $emailIds = ContactEmail::query()->orderBy('id')->pluck('id')->all();
    $phoneIds = ContactPhone::query()->orderBy('id')->pluck('id')->all();

    $second = app(UpsertContact::class)->handle($data);

    expect($second->created)->toBeFalse()
        ->and(ContactEmail::query()->orderBy('id')->pluck('id')->all())->toBe($emailIds)
        ->and(ContactPhone::query()->orderBy('id')->pluck('id')->all())->toBe($phoneIds)
        ->and(Contact::query()->count())->toBe(1)
        ->and(DB::table('contacts')->where('id', $id)->value('updated_at'))->toBe($updatedAt);
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
    DB::table('contacts')->insert([
        'id' => $id,
        'name' => 'Raced',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $result = app(UpsertContact::class)->handle(upsertData($id, 'Jane'));

    expect($result->created)->toBeFalse()
        ->and($result->contact->name)->toBe('Jane')
        ->and(ContactEmail::query()->count())->toBe(1);
});

it('handles a first create that races another request', function (): void {
    $id = (string) Str::uuid();
    $raced = false;
    DB::listen(function (QueryExecuted $query) use ($id, &$raced): void {
        if (! $raced && str_starts_with($query->sql, 'select') && preg_match('/from [`"]contacts[`"]/', $query->sql) === 1) {
            $raced = true;
            DB::table('contacts')->insert([
                'id' => $id,
                'name' => 'Raced',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    $result = app(UpsertContact::class)->handle(upsertData($id, 'Jane'));

    expect($result->created)->toBeFalse()
        ->and($result->contact->name)->toBe('Jane')
        ->and(Contact::query()->count())->toBe(1);
});

it('leaves an existing contact unchanged when a later step fails', function (): void {
    $id = (string) Str::uuid();
    app(UpsertContact::class)->handle(upsertData($id, 'Jane', ['old@x.com'], ['+61412345678']));
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'insert') && str_contains($query->sql, 'contact_phones')) {
            throw new RuntimeException('Simulated failure while syncing phones.');
        }
    });

    expect(fn () => app(UpsertContact::class)->handle(upsertData($id, 'Janet', ['new@x.com'], ['+64211234567'])))
        ->toThrow(RuntimeException::class, 'Simulated failure while syncing phones.');

    expect(Contact::query()->findOrFail($id)->name)->toBe('Jane')
        ->and(ContactEmail::query()->pluck('email')->all())->toBe(['old@x.com'])
        ->and(ContactPhone::query()->pluck('phone')->all())->toBe(['+61412345678']);
});

it('creates nothing when a later step of a first create fails', function (): void {
    $id = (string) Str::uuid();
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'insert') && str_contains($query->sql, 'contact_phones')) {
            throw new RuntimeException('Simulated failure while syncing phones.');
        }
    });

    expect(fn () => app(UpsertContact::class)->handle(upsertData($id)))
        ->toThrow(RuntimeException::class, 'Simulated failure while syncing phones.');

    $this->assertDatabaseMissing('contacts', ['id' => $id]);
    $this->assertDatabaseCount('contact_emails', 0);
});

it('treats an uppercase id as the same contact as its lowercase form', function (): void {
    $id = (string) Str::uuid();

    $first = app(UpsertContact::class)->handle(upsertData(strtoupper($id), 'Jane'));
    $second = app(UpsertContact::class)->handle(upsertData($id, 'Janet'));

    expect($first->contact->id)->toBe($id)
        ->and($second->created)->toBeFalse()
        ->and(app(GetContact::class)->handle($id)->name)->toBe('Janet')
        ->and(Contact::query()->count())->toBe(1);
});

it('touches the contact when only its emails or phones change', function (array $changes): void {
    $id = (string) Str::uuid();
    $this->travelTo('2026-11-01 10:00:00');
    app(UpsertContact::class)->handle(upsertData($id, 'Jane', ['a@x.com'], ['+61412345678']));
    $this->travelTo('2026-11-01 10:05:00');

    app(UpsertContact::class)->handle(upsertData($id, 'Jane', ...$changes));

    expect(Contact::query()->findOrFail($id)->updated_at?->toDateTimeString())->toBe('2026-11-01 10:05:00');
})->with([
    'an email added' => [[
        'emails' => ['a@x.com', 'b@x.com'],
        'phones' => ['+61412345678'],
    ]],
    'a phone replaced' => [[
        'emails' => ['a@x.com'],
        'phones' => ['+64211234567'],
    ]],
]);
