<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\ValueObjects;

use App\Modules\Contacts\Domain\Exceptions\InvalidContactId;
use Illuminate\Support\Str;
use Stringable;

/**
 * A contact's UUID, lowercased so that every database treats `ABC…` and `abc…` as the same contact.
 */
final readonly class ContactId implements Stringable
{
    private function __construct(public string $value) {}

    /**
     * @throws InvalidContactId
     */
    public static function fromString(string $value): self
    {
        if (! Str::isUuid($value)) {
            throw InvalidContactId::forValue();
        }

        return new self(strtolower($value));
    }

    /**
     * Returns null instead of throwing, for callers that treat a malformed id as "no such contact".
     */
    public static function tryFromString(string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (InvalidContactId) {
            return null;
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
