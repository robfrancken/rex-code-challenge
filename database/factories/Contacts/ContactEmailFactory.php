<?php

declare(strict_types=1);

namespace Database\Factories\Contacts;

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactEmail>
 */
final class ContactEmailFactory extends Factory
{
    protected $model = ContactEmail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = strtolower(fake()->unique()->safeEmail());

        return [
            'contact_id' => Contact::factory(),
            'email' => $email,
            'domain' => EmailAddress::fromString($email)->domain(),
        ];
    }
}
