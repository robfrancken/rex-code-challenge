<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\ContactValidator;
use Illuminate\Support\Str;

it('accepts a valid payload', function (): void {
    $payload = [
        'id' => (string) Str::uuid(),
        ...contactPayload(),
    ];

    expect(app(ContactValidator::class)->validate($payload))->toBe($payload);
});

it('rejects an invalid payload on the named key', function (
    array $overrides,
    string $key,
): void {
    $payload = [
        'id' => (string) Str::uuid(),
        ...contactPayload($overrides),
    ];

    expect(fn () => app(ContactValidator::class)->validate($payload))->toFailValidationOn($key);
})->with([
    'missing name' => [['name' => null], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
    'no emails' => [['emails' => []], 'emails'],
    'too many emails' => [['emails' => array_map(fn (int $i): string => "u{$i}@example.com", range(1, 11))], 'emails'],
    'invalid second email' => [['emails' => ['ok@example.com', 'nope']], 'emails.1'],
    'no phones' => [['phones' => []], 'phones'],
    'id not a uuid' => [['id' => 'not-a-uuid'], 'id'],
]);

it('explains the phone format when a phone is invalid', function (): void {
    $payload = [
        'id' => (string) Str::uuid(),
        ...contactPayload(['phones' => ['0412345678']]),
    ];

    expect(fn () => app(ContactValidator::class)->validate($payload))->toFailValidationOn('phones.0', 'E.164');
});

it('rejects non-string email and phone entries without erroring', function (): void {
    $payload = [
        'id' => (string) Str::uuid(),
        ...contactPayload([
            'emails' => [['x']],
            'phones' => [123],
        ]),
    ];

    expect(fn () => app(ContactValidator::class)->validate($payload))
        ->toFailValidationOn('emails.0')
        ->toFailValidationOn('phones.0');
});
