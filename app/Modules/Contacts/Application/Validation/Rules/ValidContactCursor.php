<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Pagination\Cursor;

/**
 * Accepts only a cursor the contact search issues: a name and an id, both strings.
 */
final class ValidContactCursor implements ValidationRule
{
    private const array PARAMETERS = ['id', 'name'];

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail,
    ): void {
        if (! is_string($value) || ! $this->isContactCursor(Cursor::fromEncoded($value))) {
            $fail('The :attribute is not a valid page cursor.');
        }
    }

    private function isContactCursor(?Cursor $cursor): bool
    {
        if ($cursor === null) {
            return false;
        }

        $parameters = $cursor->toArray();
        unset($parameters['_pointsToNextItems']);
        ksort($parameters);

        return array_keys($parameters) === self::PARAMETERS
            && is_string($parameters['name'])
            && is_string($parameters['id']);
    }
}
