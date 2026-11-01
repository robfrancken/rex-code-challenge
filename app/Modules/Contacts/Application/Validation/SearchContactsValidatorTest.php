<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Illuminate\Validation\ValidationException;

it('accepts valid filters', function (): void {
    $input = [
        'name' => 'jane',
        'phone' => '+6141',
        'email_domain' => 'rex.com.au',
        'page' => 2,
        'per_page' => 100,
    ];

    expect(app(SearchContactsValidator::class)->validate($input))->toBe([
        ...$input,
        'phone' => '6141',
    ]);
});

it('normalises phone to digits and lowercases the domain after validating', function (): void {
    $validated = app(SearchContactsValidator::class)->validate([
        'phone' => '+61 (4) 1234-5678',
        'email_domain' => 'REX.com.au',
    ]);

    expect($validated['phone'])->toBe('61412345678')
        ->and($validated['email_domain'])->toBe('rex.com.au');
});

it('drops one leading trunk zero from the phone', function (
    string $phone,
    string $expected,
): void {
    expect(app(SearchContactsValidator::class)->validate(['phone' => $phone])['phone'])->toBe($expected);
})->with([
    'local mobile' => [
        '(04) 1234-5678',
        '412345678',
    ],
    'only one zero' => [
        '00412',
        '0412',
    ],
    'a lone zero is kept' => [
        '0',
        '0',
    ],
]);

it('rejects invalid filters', function (
    array $input,
    string $key,
): void {
    try {
        app(SearchContactsValidator::class)->validate($input);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    $this->fail('Expected a ValidationException.');
})->with([
    'per_page too large' => [['per_page' => 101], 'per_page'],
    'page zero' => [['page' => 0], 'page'],
    'phone not numeric' => [['phone' => 'abc'], 'phone'],
]);
