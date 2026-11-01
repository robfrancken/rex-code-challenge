<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

final readonly class UpsertContactData
{
    /**
     * @param  list<EmailAddress>  $emails
     * @param  list<PhoneNumber>  $phones
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $emails,
        public array $phones,
    ) {}

    /**
     * @param  array{id: string, name: string, emails: list<string>, phones: list<string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            $validated['id'],
            $validated['name'],
            self::uniqueByValue(array_map(EmailAddress::fromString(...), $validated['emails'])),
            self::uniqueByValue(array_map(PhoneNumber::fromString(...), $validated['phones'])),
        );
    }

    /**
     * @template T of EmailAddress|PhoneNumber
     *
     * @param  list<T>  $items
     * @return list<T>
     */
    private static function uniqueByValue(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $unique[$item->value] ??= $item;
        }

        return array_values($unique);
    }
}
