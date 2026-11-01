<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

use App\Modules\Contacts\Domain\Models\Contact;

final readonly class UpsertResult
{
    public function __construct(
        public Contact $contact,
        public bool $created,
    ) {}
}
