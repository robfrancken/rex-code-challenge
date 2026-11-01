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
    new BulkContactSeeder()->run(250);

    expect(Contact::count())->toBe(250);
});

it('stores only valid phones and emails with matching domains', function (): void {
    new BulkContactSeeder()->run(250);

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
    new BulkContactSeeder()->run(250);

    expect(Contact::has('emails', '<', 1)->count())->toBe(0)
        ->and(Contact::has('emails', '>', 3)->count())->toBe(0)
        ->and(Contact::has('phones', '<', 1)->count())->toBe(0)
        ->and(Contact::has('phones', '>', 2)->count())->toBe(0);
});

it('is idempotent', function (): void {
    new BulkContactSeeder()->run(250);
    $counts = [Contact::count(), ContactEmail::count(), ContactPhone::count()];

    new BulkContactSeeder()->run(250);

    expect([Contact::count(), ContactEmail::count(), ContactPhone::count()])->toBe($counts);
});

it('inserts in batches rather than per row', function (): void {
    DB::enableQueryLog();

    new BulkContactSeeder()->run(250);

    $inserts = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with($query['query'], 'insert'));

    expect($inserts->count())->toBeLessThanOrEqual(3);
});

it('defaults the count from config', function (): void {
    config(['contacts.seed.bulk_contacts' => 30]);

    new BulkContactSeeder()->run();

    expect(Contact::count())->toBe(30);
});
