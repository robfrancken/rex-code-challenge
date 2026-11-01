<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Exceptions;

use App\Exceptions\ProblemException;

final class ContactNotFound extends ProblemException
{
    public static function withId(string $id): self
    {
        return new self("No contact exists with id {$id}.");
    }

    public function problemCode(): string
    {
        return 'contact_not_found';
    }

    public function status(): int
    {
        return 404;
    }

    public function title(): string
    {
        return 'Contact not found';
    }
}
