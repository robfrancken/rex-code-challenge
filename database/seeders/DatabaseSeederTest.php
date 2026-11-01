<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Domain\Models\Contact;
use Database\Seeders\DatabaseSeeder;

it('can be run twice without duplicating the demo user or contacts', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'demo@example.com')->count())->toBe(1)
        ->and(Contact::count())->toBe(40);
});
