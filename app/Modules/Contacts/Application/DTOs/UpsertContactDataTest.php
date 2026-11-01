<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Domain\ValueObjects\ContactId;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

it('rejects a contact without a name, emails or phones', function (
    string $name,
    array $emails,
    array $phones,
): void {
    new UpsertContactData(ContactId::fromString('8f14e45f-ceea-467a-9575-1c5a3f2b0d61'), $name, $emails, $phones);
})->with([
    'no emails' => fn (): array => [
        'Jane',
        [],
        [PhoneNumber::fromString('+61412345678')],
    ],
    'no phones' => fn (): array => [
        'Jane',
        [EmailAddress::fromString('jane@example.com')],
        [],
    ],
    'blank name' => fn (): array => [
        '   ',
        [EmailAddress::fromString('jane@example.com')],
        [PhoneNumber::fromString('+61412345678')],
    ],
])->throws(InvalidArgumentException::class);

it('trims the name', function (): void {
    $data = new UpsertContactData(
        ContactId::fromString('8f14e45f-ceea-467a-9575-1c5a3f2b0d61'),
        '  Jane ',
        [EmailAddress::fromString('jane@example.com')],
        [PhoneNumber::fromString('+61412345678')],
    );

    expect($data->name)->toBe('Jane');
});
