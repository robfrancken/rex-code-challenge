<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Str;

it('accepts valid filters', function (): void {
    $input = [
        'q' => 'jane',
        'name' => 'jane',
        'phone' => '+6141',
        'email_domain' => 'rex.com.au',
        'per_page' => 100,
        'cursor' => (new Cursor([
            'name' => 'Jane',
            'id' => 'abc',
        ]))->encode(),
    ];

    expect(app(SearchContactsValidator::class)->validate($input))->toBe($input);
});

it('lowercases the domain after validating and leaves the phone raw', function (): void {
    $validated = app(SearchContactsValidator::class)->validate([
        'phone' => '+61 (4) 1234-5678',
        'email_domain' => 'REX.com.au',
    ]);

    expect($validated['phone'])->toBe('+61 (4) 1234-5678')
        ->and($validated['email_domain'])->toBe('rex.com.au');
});

it('strips a leading @ from the domain', function (): void {
    $validated = app(SearchContactsValidator::class)->validate(['email_domain' => ' @Rex.com.au ']);

    expect($validated['email_domain'])->toBe('rex.com.au');
});

it('accepts the cursor of a contact whose name is long in JSON', function (int $characters): void {
    $cursor = (new Cursor([
        'name' => str_repeat('😀', $characters),
        'id' => (string) Str::uuid(),
    ]))->encode();

    expect(app(SearchContactsValidator::class)->validate(['cursor' => $cursor]))->toBe(['cursor' => $cursor]);
})->with([
    '200 emoji' => 200,
    'the longest name, 255 emoji' => 255,
]);

it('rejects invalid filters', function (
    array $input,
    string $key,
): void {
    expect(fn () => app(SearchContactsValidator::class)->validate($input))->toFailValidationOn($key);
})->with([
    'per_page too large' => [['per_page' => 101], 'per_page'],
    'phone not numeric' => [['phone' => 'abc'], 'phone'],
    'q too long' => [['q' => str_repeat('a', 256)], 'q'],
    'cursor garbage' => [['cursor' => 'garbage'], 'cursor'],
    'cursor too long' => [['cursor' => str_repeat('a', 4401)], 'cursor'],
]);

it('ignores the removed page parameter', function (): void {
    expect(app(SearchContactsValidator::class)->validate(['page' => 2]))->toBe([]);
});
