<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation;

use App\Modules\Contacts\Application\Search\SearchTerms;
use App\Modules\Contacts\Application\Validation\Rules\ValidContactCursor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SearchContactsValidator
{
    /**
     * Room for the longest cursor a contact can produce: a 255-character name of four-byte characters,
     * each JSON-escaped as a 12-character surrogate pair, plus the id, base64-encoded (4,188 characters).
     */
    private const int MAX_CURSOR_LENGTH = 4400;

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
            'phone' => ['nullable', 'string', 'max:25', 'regex:'.SearchTerms::PHONE_PATTERN],
            'email_domain' => ['nullable', 'string', 'max:253'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'cursor' => ['nullable', 'string', 'max:'.self::MAX_CURSOR_LENGTH, new ValidContactCursor],
        ])->validate();

        if (isset($validated['email_domain'])) {
            $validated['email_domain'] = ltrim(strtolower(trim($validated['email_domain'])), '@');
        }

        return $validated;
    }
}
