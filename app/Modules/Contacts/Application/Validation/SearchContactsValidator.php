<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use App\Modules\Contacts\Application\Validation\Rules\ValidContactCursor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SearchContactsValidator
{
    /**
     * `per_page` passes the integer rule as an int or numeric string (query strings and CLI options are strings).
     *
     * @param  array<string, mixed>  $input
     * @return array{q?: string|null, name?: string|null, phone?: string|null, email_domain?: string|null, per_page?: int|numeric-string|null, cursor?: string|null}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array{q?: string|null, name?: string|null, phone?: string|null, email_domain?: string|null, per_page?: int|numeric-string|null, cursor?: string|null} $validated */
        $validated = Validator::make($input, [
            'q' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^\+?[\s\-(]*[0-9][0-9\s\-()]*$/'],
            'email_domain' => ['nullable', 'string', 'max:253'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:512', new ValidContactCursor],
        ])->validate();

        if (isset($validated['email_domain'])) {
            $validated['email_domain'] = strtolower(trim($validated['email_domain']));
        }

        return $validated;
    }
}
