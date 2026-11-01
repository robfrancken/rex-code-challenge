<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation\Rules;

use App\Modules\Contacts\Domain\Exceptions\InvalidEmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidEmailAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            EmailAddress::fromString($value);
        } catch (InvalidEmailAddress) {
            $fail('The :attribute must be a valid email address.');
        }
    }
}
