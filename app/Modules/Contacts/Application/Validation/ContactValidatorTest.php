<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\ContactValidator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validContactPayload(array $overrides = []): array
{
    return [
        'id' => (string) Str::uuid(),
        'name' => 'Jane Smith',
        'emails' => ['jane@example.com'],
        'phones' => ['+61412345678'],
        ...$overrides,
    ];
}

it('accepts a valid payload', function (): void {
    $payload = validContactPayload();

    expect(app(ContactValidator::class)->validate($payload))->toBe($payload);
});

it('rejects an invalid payload on the named key', function (
    array $overrides,
    string $key,
): void {
    try {
        app(ContactValidator::class)->validate(validContactPayload($overrides));
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    $this->fail('Expected a ValidationException.');
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
    try {
        app(ContactValidator::class)->validate(validContactPayload(['phones' => ['0412345678']]));
    } catch (ValidationException $exception) {
        expect($exception->errors()['phones.0'][0])->toContain('E.164');

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('rejects non-string email and phone entries without erroring', function (): void {
    expect(fn () => app(ContactValidator::class)->validate(validContactPayload([
        'emails' => [['x']],
        'phones' => [123],
    ])))
        ->toThrow(ValidationException::class);
});
