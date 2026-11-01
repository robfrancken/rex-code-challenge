<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Exceptions;

use App\Exceptions\ApplicationException;

final class ContactNotFound extends ApplicationException
{
    public static function withId(string $id): self
    {
        return new self("No contact exists with id {$id}.");
    }

    public function problemCode(): string
    {
        return 'contact_not_found';
    }
}
