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
        /** @var array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|null, per_page?: int|null} $validated */
        $validated = Validator::make($input, [
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^\+?[\s\-(]*[0-9][0-9\s\-()]*$/'],
            'email_domain' => ['nullable', 'string', 'max:253'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ])->validate();

        return $this->normalise($validated);
    }

    /**
     * Runs after validation: reduces the phone to digits and lowercases the domain, for every input source.
     *
     * @param  array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|null, per_page?: int|null}  $validated
     * @return array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|null, per_page?: int|null}
     */
    private function normalise(array $validated): array
    {
        if (isset($validated['phone'])) {
            $validated['phone'] = preg_replace('/\D/', '', $validated['phone']);
        }

        if (isset($validated['email_domain'])) {
            $validated['email_domain'] = strtolower(trim($validated['email_domain']));
        }

        return $validated;
    }
}
