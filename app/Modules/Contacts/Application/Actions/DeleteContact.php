<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;

final class DeleteContact
{
    /**
     * @throws ContactNotFound
     */
    public function handle(string $id): void
    {
        $contactId = ContactId::tryFromString($id) ?? throw ContactNotFound::withId($id);

        if (Contact::query()->whereKey($contactId->value)->delete() === 0) {
            throw ContactNotFound::withId($id);
        }
    }
}
