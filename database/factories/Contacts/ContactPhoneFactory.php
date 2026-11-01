<?php

declare(strict_types=1);

namespace Database\Factories\Contacts;

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactPhone>
 */
final class ContactPhoneFactory extends Factory
{
    protected $model = ContactPhone::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'phone' => '+614'.fake()->unique()->numerify('########'),
        ];
    }
}
