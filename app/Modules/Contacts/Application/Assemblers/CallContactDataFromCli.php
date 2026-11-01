<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Assemblers;

use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Validation\CallContactValidator;
use Illuminate\Validation\ValidationException;

final readonly class CallContactDataFromCli
{
    public function __construct(private CallContactValidator $validator) {}

    /**
     * @throws ValidationException
     */
    public function assemble(
        string $contactId,
        ?string $phone,
    ): CallContactData {
        return CallContactData::fromValidated($contactId, $this->validator->validate(['phone' => $phone]));
    }
}
