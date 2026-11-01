<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use App\Modules\Contacts\Application\Validation\Rules\ValidPhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CallValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{phone?: string|null}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array{phone?: string|null} */
        return Validator::make($input, [
            'phone' => ['nullable', 'string', new ValidPhoneNumber],
        ])->validate();
    }
}
