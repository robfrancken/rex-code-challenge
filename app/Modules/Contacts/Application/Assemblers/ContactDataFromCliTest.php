<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\ContactDataFromCli;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('generates a uuid when no id is given', function (): void {
    $data = app(ContactDataFromCli::class)->assemble(null, 'Jane', ['jane@example.com'], ['+61412345678']);

    expect($data->id->value)->toBeUuid();
});

it('keeps a given id', function (): void {
    $id = (string) Str::uuid();

    $data = app(ContactDataFromCli::class)->assemble($id, 'Jane', ['jane@example.com'], ['+61412345678']);

    expect($data->id->value)->toBe($id);
});

it('trims the name', function (): void {
    $data = app(ContactDataFromCli::class)->assemble(null, '  Jane ', ['jane@example.com'], ['+61412345678']);

    expect($data->name)->toBe('Jane');
});

it('throws a validation exception for invalid input', function (): void {
    app(ContactDataFromCli::class)->assemble(null, 'Jane', ['bad'], ['+61412345678']);
})->throws(ValidationException::class);
