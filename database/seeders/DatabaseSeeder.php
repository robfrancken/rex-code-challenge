<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. The bulk fake contacts are left out in production.
     */
    public function run(): void
    {
        User::query()->where('email', 'demo@example.com')->firstOr(fn (): User => User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
        ]));

        $this->call(ContactSeeder::class);

        if (! app()->isProduction()) {
            $this->call(BulkContactSeeder::class);
        }
    }
}
