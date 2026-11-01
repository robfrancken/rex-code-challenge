<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use App\Modules\Contacts\Application\Validation\Rules\ValidEmailAddress;
use App\Modules\Contacts\Application\Validation\Rules\ValidPhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class ContactValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{id: string, name: string, emails: list<string>, phones: list<string>}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array{id: string, name: string, emails: list<string>, phones: list<string>} */
        return Validator::make($input, [
            'id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'emails' => ['required', 'array', 'min:1', 'max:10'],
            'emails.*' => ['required', 'string', 'max:254', new ValidEmailAddress],
            'phones' => ['required', 'array', 'min:1', 'max:10'],
            'phones.*' => ['required', 'string', new ValidPhoneNumber],
        ])->validate();
    }
}
