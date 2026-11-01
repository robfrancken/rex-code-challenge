<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\ValueObjects;

use App\Modules\Contacts\Domain\Exceptions\InvalidEmailAddress;
use Stringable;

final readonly class EmailAddress implements Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $normalised = strtolower(trim($value));

        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidEmailAddress::forValue();
        }

        return new self($normalised);
    }

    public function domain(): string
    {
        return substr($this->value, (int) strrpos($this->value, '@') + 1);
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
