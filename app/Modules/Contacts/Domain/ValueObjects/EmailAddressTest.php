<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Exceptions\InvalidEmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;

it('trims and lowercases an address and exposes its domain', function (): void {
    $email = EmailAddress::fromString(' Jo@Rex.COM.au ');

    expect($email->value)->toBe('jo@rex.com.au')
        ->and((string) $email)->toBe('jo@rex.com.au')
        ->and($email->domain())->toBe('rex.com.au');
});

it('rejects invalid addresses', function (string $input): void {
    EmailAddress::fromString($input);
})->with(['not-an-email', 'a@'])->throws(InvalidEmailAddress::class);

it('compares by normalised value', function (): void {
    expect(EmailAddress::fromString('A@b.com')->equals(EmailAddress::fromString(' a@B.com ')))->toBeTrue();
});
