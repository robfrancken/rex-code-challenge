<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\ValueObjects;

use App\Modules\Contacts\Domain\Exceptions\InvalidPhoneNumber;
use Stringable;

final readonly class PhoneNumber implements Stringable
{
    private const string AU_PATTERN = '/^\+61[2-478]\d{8}$/';

    private const string NZ_PATTERN = '/^\+64[2-9]\d{7,9}$/';

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if (preg_match(self::AU_PATTERN, $trimmed) !== 1 && preg_match(self::NZ_PATTERN, $trimmed) !== 1) {
            throw InvalidPhoneNumber::forValue();
        }

        return new self($trimmed);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
