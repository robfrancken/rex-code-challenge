<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

final readonly class SearchContactsData
{
    public function __construct(
        public ?string $q,
        public ?string $name,
        public ?string $phone,
        public ?string $emailDomain,
        public int $perPage = 25,
        public ?string $cursor = null,
    ) {}

    /**
     * @param  array{q?: string|null, name?: string|null, phone?: string|null, email_domain?: string|null, per_page?: int|string|null, cursor?: string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            self::filled($validated['q'] ?? null),
            self::filled($validated['name'] ?? null),
            self::filled($validated['phone'] ?? null),
            self::filled($validated['email_domain'] ?? null),
            (int) ($validated['per_page'] ?? 25),
            self::filled($validated['cursor'] ?? null),
        );
    }

    private static function filled(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
