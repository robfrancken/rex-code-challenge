<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\CallContactValidator;

it('accepts a missing phone', function (): void {
    expect(app(CallContactValidator::class)->validate(['phone' => null]))->toBe(['phone' => null]);
});

it('accepts a valid phone', function (): void {
    expect(app(CallContactValidator::class)->validate(['phone' => '+61412345678']))->toBe(['phone' => '+61412345678']);
});

it('rejects an invalid phone', function (): void {
    expect(fn () => app(CallContactValidator::class)->validate(['phone' => '0412345678']))->toFailValidationOn('phone');
});
