<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Assemblers;

use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Application\Validation\ContactValidator;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;
use Illuminate\Validation\ValidationException;

final readonly class ContactDataFromCli
{
    public function __construct(private ContactValidator $validator) {}

    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     *
     * @throws ValidationException
     */
    public function assemble(
        ?string $id,
        string $name,
        array $emails,
        array $phones,
    ): UpsertContactData {
        return UpsertContactData::fromValidated($this->validator->validate([
            'id' => $id ?? ContactId::generate()->value,
            'name' => $name,
            'emails' => $emails,
            'phones' => $phones,
        ]));
    }
}
