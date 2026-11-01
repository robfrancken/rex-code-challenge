<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\CallContactValidator;
use Illuminate\Validation\ValidationException;

it('accepts a missing phone', function (): void {
    expect(app(CallContactValidator::class)->validate(['phone' => null]))->toBe(['phone' => null]);
});

it('accepts a valid phone', function (): void {
    expect(app(CallContactValidator::class)->validate(['phone' => '+61412345678']))->toBe(['phone' => '+61412345678']);
});

it('rejects an invalid phone', function (): void {
    try {
        app(CallContactValidator::class)->validate(['phone' => '0412345678']);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('phone');

        return;
    }

    $this->fail('Expected a ValidationException.');
});
