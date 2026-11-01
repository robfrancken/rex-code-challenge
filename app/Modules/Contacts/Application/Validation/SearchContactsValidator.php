<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SearchContactsValidator
{
    /**
     * `page` and `per_page` pass the integer rule as ints or numeric strings (query strings and CLI options are strings).
     *
     * @param  array<string, mixed>  $input
     * @return array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|numeric-string|null, per_page?: int|numeric-string|null}
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        /** @var array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|numeric-string|null, per_page?: int|numeric-string|null} $validated */
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
     * Runs after validation: reduces the phone to search digits and lowercases the domain, for every input source.
     *
     * @param  array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|numeric-string|null, per_page?: int|numeric-string|null}  $validated
     * @return array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|numeric-string|null, per_page?: int|numeric-string|null}
     */
    private function normalise(array $validated): array
    {
        if (isset($validated['phone'])) {
            $validated['phone'] = $this->searchDigits($validated['phone']);
        }

        if (isset($validated['email_domain'])) {
            $validated['email_domain'] = strtolower(trim($validated['email_domain']));
        }

        return $validated;
    }

    /**
     * Strips formatting, then drops one leading trunk `0` so a local number such as `0412 345 678`
     * matches the stored E.164 `+61412345678`.
     */
    private function searchDigits(string $phone): string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (strlen($digits) > 1 && str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
