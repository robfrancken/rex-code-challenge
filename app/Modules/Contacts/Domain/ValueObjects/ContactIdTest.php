<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Exceptions\InvalidContactId;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;

it('lowercases a valid uuid', function (): void {
    $id = ContactId::fromString('8F14E45F-CEEA-467A-9575-1C5A3F2B0D61');

    expect($id->value)->toBe('8f14e45f-ceea-467a-9575-1c5a3f2b0d61')
        ->and((string) $id)->toBe('8f14e45f-ceea-467a-9575-1c5a3f2b0d61');
});

it('rejects a value that is not a uuid', function (string $input): void {
    ContactId::fromString($input);
})->with([
    'not-a-uuid',
    '8f14e45f-ceea-467a-9575-1c5a3f2b0d6',
    ' 8f14e45f-ceea-467a-9575-1c5a3f2b0d61',
    '',
])->throws(InvalidContactId::class);

it('returns null from tryFromString for a value that is not a uuid', function (): void {
    expect(ContactId::tryFromString('abc'))->toBeNull()
        ->and(ContactId::tryFromString('8F14E45F-CEEA-467A-9575-1C5A3F2B0D61')?->value)->toBe('8f14e45f-ceea-467a-9575-1c5a3f2b0d61');
});
