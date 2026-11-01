<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromCli;
use Illuminate\Validation\ValidationException;

it('maps CLI option names onto the DTO', function (): void {
    $data = app(SearchContactsDataFromCli::class)->assemble([
        'name' => 'jane',
        'phone' => '+61 412',
        'domain' => 'REX.com.au',
        'page' => '2',
        'per-page' => '10',
    ]);

    expect($data->name)->toBe('jane')
        ->and($data->phoneDigits)->toBe('61412')
        ->and($data->emailDomain)->toBe('rex.com.au')
        ->and($data->page)->toBe(2)
        ->and($data->perPage)->toBe(10);
});

it('uses defaults when options are absent', function (): void {
    $data = app(SearchContactsDataFromCli::class)->assemble([
        'name' => null,
        'per-page' => null,
    ]);

    expect($data->name)->toBeNull()
        ->and($data->page)->toBe(1)
        ->and($data->perPage)->toBe(25);
});

it('rejects a phone filter that has no digits', function (): void {
    try {
        app(SearchContactsDataFromCli::class)->assemble(['phone' => 'abc']);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('phone');

        return;
    }

    $this->fail('Expected a ValidationException.');
});
