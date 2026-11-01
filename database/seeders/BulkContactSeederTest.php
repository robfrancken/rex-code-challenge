<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Database\Seeders\BulkContactSeeder;
use Illuminate\Support\Facades\DB;

it('seeds the requested number of contacts', function (): void {
    new BulkContactSeeder()->run(25);

    expect(Contact::count())->toBe(25);
});

it('stores only valid phones and emails with matching domains', function (): void {
    new BulkContactSeeder()->run(25);

    ContactPhone::all()->each(function (ContactPhone $phone): void {
        expect(PhoneNumber::fromString($phone->phone)->value)->toBe($phone->phone);
    });
    ContactEmail::all()->each(function (ContactEmail $email): void {
        $address = EmailAddress::fromString($email->email);

        expect($address->value)->toBe($email->email)
            ->and($address->domain())->toBe($email->domain);
    });
});

it('gives each contact one to three emails and one to two phones', function (): void {
    new BulkContactSeeder()->run(25);

    expect(Contact::has('emails', '<', 1)->count())->toBe(0)
        ->and(Contact::has('emails', '>', 3)->count())->toBe(0)
        ->and(Contact::has('phones', '<', 1)->count())->toBe(0)
        ->and(Contact::has('phones', '>', 2)->count())->toBe(0);
});

it('is idempotent', function (): void {
    new BulkContactSeeder()->run(25);
    $counts = [Contact::count(), ContactEmail::count(), ContactPhone::count()];

    new BulkContactSeeder()->run(25);

    expect([Contact::count(), ContactEmail::count(), ContactPhone::count()])->toBe($counts);
});

it('inserts in batches rather than per row', function (): void {
    DB::enableQueryLog();

    new BulkContactSeeder()->run(25);

    $inserts = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'insert'));

    expect($inserts->count())->toBeLessThanOrEqual(3);
});

it('defaults the count from config', function (): void {
    config(['contacts.seed.bulk_contacts' => 30]);

    new BulkContactSeeder()->run();

    expect(Contact::count())->toBe(30);
});

it('refuses to run in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => new BulkContactSeeder()->run(5))->toThrow(RuntimeException::class, 'SEED_BULK_CONTACTS_IN_PRODUCTION')
        ->and(Contact::count())->toBe(0);
});

it('runs in production when forced', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    config(['contacts.seed.bulk_in_production' => true]);

    new BulkContactSeeder()->run(5);

    expect(Contact::count())->toBe(5);
});
