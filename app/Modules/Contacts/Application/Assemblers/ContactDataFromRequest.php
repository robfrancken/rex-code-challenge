<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Assemblers;

use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Application\Validation\ContactValidator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final readonly class ContactDataFromRequest
{
    public function __construct(private ContactValidator $validator) {}

    /**
     * @throws ValidationException
     */
    public function assemble(Request $request, string $id): UpsertContactData
    {
        return UpsertContactData::fromValidated(
            $this->validator->validate(['id' => $id] + $request->only('name', 'emails', 'phones')),
        );
    }
}
