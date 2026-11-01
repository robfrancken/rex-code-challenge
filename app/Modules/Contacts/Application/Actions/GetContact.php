<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Support\Str;

final class GetContact
{
    /**
     * @throws ContactNotFound
     */
    public function handle(string $id): Contact
    {
        if (! Str::isUuid($id)) {
            throw ContactNotFound::withId($id);
        }

        return Contact::query()->with(['emails', 'phones'])->find($id) ?? throw ContactNotFound::withId($id);
    }
}
