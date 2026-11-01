<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;

final class GetContact
{
    /**
     * @throws ContactNotFound
     */
    public function handle(string $id): Contact
    {
        return Contact::query()->with(['emails', 'phones'])->find($id) ?? throw ContactNotFound::withId($id);
    }
}
