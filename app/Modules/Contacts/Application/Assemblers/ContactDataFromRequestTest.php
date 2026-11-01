<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\ContactDataFromRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('assembles data from a valid request', function (): void {
    $id = (string) Str::uuid();
    $request = Request::create('/', 'PUT', [
        'name' => 'Jane',
        'emails' => ['Jane@Example.com'],
        'phones' => ['+61412345678'],
    ]);

    $data = app(ContactDataFromRequest::class)->assemble($request, $id);

    expect($data->id->value)->toBe($id)
        ->and($data->emails[0]->value)->toBe('jane@example.com');
});

it('throws a validation exception for an invalid payload', function (): void {
    $request = Request::create('/', 'PUT', ['name' => 'Jane']);

    app(ContactDataFromRequest::class)->assemble($request, (string) Str::uuid());
})->throws(ValidationException::class);
