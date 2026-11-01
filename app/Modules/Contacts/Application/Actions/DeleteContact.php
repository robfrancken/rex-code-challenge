<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;

final class DeleteContact
{
    /**
     * @throws ContactNotFound
     */
    public function handle(string $id): void
    {
        if (Contact::query()->whereKey($id)->delete() === 0) {
            throw ContactNotFound::withId($id);
        }
    }
}
