<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SearchContactsValidator
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|null, per_page?: int|null}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|null, per_page?: int|null} */
        return Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'email_domain' => ['nullable', 'string', 'max:253'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();
    }

    /**
     * Strips non-digits from the phone and lowercases the domain, so every input source filters identically.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalise(array $input): array
    {
        if (is_string($input['phone'] ?? null)) {
            $input['phone'] = preg_replace('/\D/', '', $input['phone']);
        }

        if (is_string($input['email_domain'] ?? null)) {
            $input['email_domain'] = strtolower(trim($input['email_domain']));
        }

        return $input;
    }
}
