<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

use App\Modules\Contacts\Domain\ValueObjects\ContactId;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use InvalidArgumentException;

final readonly class UpsertContactData
{
    public string $name;

    /**
     * @param  list<EmailAddress>  $emails
     * @param  list<PhoneNumber>  $phones
     *
     * @throws InvalidArgumentException when the name is blank or there is no email or phone
     */
    public function __construct(
        public ContactId $id,
        string $name,
        public array $emails,
        public array $phones,
    ) {
        $this->name = trim($name);

        if ($this->name === '') {
            throw new InvalidArgumentException('A contact needs a name.');
        }

        if ($emails === []) {
            throw new InvalidArgumentException('A contact needs at least one email address.');
        }

        if ($phones === []) {
            throw new InvalidArgumentException('A contact needs at least one phone number.');
        }
    }

    /**
     * @param  array{id: string, name: string, emails: list<string>, phones: list<string>}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            ContactId::fromString($validated['id']),
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
