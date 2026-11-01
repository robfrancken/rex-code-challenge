<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

final readonly class CallContactData
{
    public function __construct(
        public string $contactId,
        public ?PhoneNumber $phone,
    ) {}

    /**
     * @param  array{phone?: string|null}  $validated
     */
    public static function fromValidated(
        string $contactId,
        array $validated,
    ): self {
        $phone = $validated['phone'] ?? null;

        return new self($contactId, $phone === null ? null : PhoneNumber::fromString($phone));
    }
}
