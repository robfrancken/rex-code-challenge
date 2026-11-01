<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Database\Seeders\ContactSeeder;

it('seeds forty contacts', function (): void {
    $this->seed(ContactSeeder::class);

    expect(Contact::count())->toBe(40);
});

it('stores only valid phones and emails', function (): void {
    $this->seed(ContactSeeder::class);

    ContactPhone::all()->each(function (ContactPhone $phone): void {
        expect(PhoneNumber::fromString($phone->phone)->value)->toBe($phone->phone);
    });
    ContactEmail::all()->each(function (ContactEmail $email): void {
        expect(EmailAddress::fromString($email->email)->value)->toBe($email->email);
    });
});

it('mixes domains and countries', function (): void {
    $this->seed(ContactSeeder::class);

    expect(ContactEmail::where('domain', 'rex.com.au')->count())->toBeGreaterThanOrEqual(8)
        ->and(ContactEmail::where('domain', 'example.co.nz')->count())->toBeGreaterThanOrEqual(4)
        ->and(ContactPhone::where('phone', 'like', '+642%')->distinct()->count('contact_id'))->toBe(10)
        ->and(Contact::has('phones', '>=', 2)->count())->toBeGreaterThanOrEqual(6);
});

it('is idempotent', function (): void {
    $this->seed(ContactSeeder::class);
    $this->seed(ContactSeeder::class);

    expect(Contact::count())->toBe(40);
});
