<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromCli;

it('maps CLI argument and option names onto the DTO', function (): void {
    $data = app(SearchContactsDataFromCli::class)->assemble([
        'q' => 'jane',
        'name' => 'jane',
        'phone' => '0412 345',
        'domain' => 'REX.com.au',
        'cursor' => null,
        'per-page' => '10',
    ]);

    expect($data->q)->toBe('jane')
        ->and($data->name)->toBe('jane')
        ->and($data->phone)->toBe('0412 345')
        ->and($data->emailDomain)->toBe('rex.com.au')
        ->and($data->cursor)->toBeNull()
        ->and($data->perPage)->toBe(10);
});

it('uses defaults when options are absent', function (): void {
    $data = app(SearchContactsDataFromCli::class)->assemble([
        'name' => null,
        'per-page' => null,
    ]);

    expect($data->name)->toBeNull()
        ->and($data->q)->toBeNull()
        ->and($data->perPage)->toBe(25);
});

it('rejects a phone filter that has no digits', function (): void {
    expect(fn () => app(SearchContactsDataFromCli::class)->assemble(['phone' => 'abc']))->toFailValidationOn('phone');
});

it('rejects a malformed cursor', function (): void {
    expect(fn () => app(SearchContactsDataFromCli::class)->assemble(['cursor' => 'garbage']))->toFailValidationOn('cursor');
});
