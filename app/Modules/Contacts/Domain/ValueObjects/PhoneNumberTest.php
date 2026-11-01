<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Exceptions\InvalidPhoneNumber;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

it('accepts valid AU and NZ numbers', function (
    string $input,
    string $expected,
): void {
    $phone = PhoneNumber::fromString($input);

    expect($phone->value)->toBe($expected)
        ->and((string) $phone)->toBe($expected);
})->with([
    ['+61412345678', '+61412345678'],
    ['+61298765432', '+61298765432'],
    ['+6421234567', '+6421234567'],
    ['+642123456789', '+642123456789'],
    ['+6493012345', '+6493012345'],
    [' +61412345678 ', '+61412345678'],
]);

it('rejects invalid numbers', function (string $input): void {
    PhoneNumber::fromString($input);
})->with([
    '0412345678',
    '+61 412 345 678',
    '+1415555 0100',
    '+4420794600 00',
    '+6141234567',
    '+614123456789',
    '+61512345678',
    '+6411234567',
    '',
])->throws(InvalidPhoneNumber::class);

it('compares by value', function (): void {
    expect(PhoneNumber::fromString('+61412345678')->equals(PhoneNumber::fromString(' +61412345678')))->toBeTrue()
        ->and(PhoneNumber::fromString('+61412345678')->equals(PhoneNumber::fromString('+61412345679')))->toBeFalse();
});
