<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

final readonly class SearchContactsData
{
    public function __construct(
        public ?string $name,
        public ?string $phoneDigits,
        public ?string $emailDomain,
        public int $page = 1,
        public int $perPage = 25,
    ) {}

    /**
     * @param  array{name?: string|null, phone?: string|null, email_domain?: string|null, page?: int|string|null, per_page?: int|string|null}  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            self::filled($validated['name'] ?? null),
            self::filled($validated['phone'] ?? null),
            self::filled($validated['email_domain'] ?? null),
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 25),
        );
    }

    private static function filled(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
