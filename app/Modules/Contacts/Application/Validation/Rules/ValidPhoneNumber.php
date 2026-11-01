<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Validation\Rules;

use App\Modules\Contacts\Domain\Exceptions\InvalidPhoneNumber;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        try {
            PhoneNumber::fromString($value);
        } catch (InvalidPhoneNumber) {
            $fail('The :attribute must be an E.164 Australian (+61) or New Zealand (+64) number, for example +61412345678.');
        }
    }
}
