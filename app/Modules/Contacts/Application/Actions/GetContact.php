<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;

final class GetContact
{
    /**
     * @throws ContactNotFound
     */
    public function handle(string $id): Contact
    {
        $contactId = ContactId::tryFromString($id) ?? throw ContactNotFound::withId($id);

        return Contact::query()->with(['emails', 'phones'])->find($contactId->value) ?? throw ContactNotFound::withId($id);
    }
}
