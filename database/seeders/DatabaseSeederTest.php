<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Domain\Models\Contact;
use Database\Seeders\DatabaseSeeder;

it('can be run twice without duplicating the demo user or contacts', function (): void {
    config(['contacts.seed.bulk_contacts' => 10]);

    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1)
        ->and(Contact::count())->toBe(50);
});

it('leaves out the bulk contacts in production', function (): void {
    config(['contacts.seed.bulk_contacts' => 10]);
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('db:seed', [
        '--class' => DatabaseSeeder::class,
        '--force' => true,
    ])->assertSuccessful();

    expect(Contact::count())->toBe(40);
});
