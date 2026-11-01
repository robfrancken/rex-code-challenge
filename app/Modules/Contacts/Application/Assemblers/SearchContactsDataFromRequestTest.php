<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromRequest;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;

it('maps q and cursor and lowercases the domain', function (): void {
    $cursor = (new Cursor([
        'name' => 'Jane',
        'id' => 'abc',
    ]))->encode();
    $request = Request::create('/', 'GET', [
        'q' => 'rex',
        'phone' => '+61 412',
        'email_domain' => 'REX.com.au',
        'cursor' => $cursor,
    ]);

    $data = app(SearchContactsDataFromRequest::class)->assemble($request);

    expect($data->q)->toBe('rex')
        ->and($data->phone)->toBe('+61 412')
        ->and($data->emailDomain)->toBe('rex.com.au')
        ->and($data->cursor)->toBe($cursor)
        ->and($data->perPage)->toBe(25);
});

it('treats blank filters as absent', function (): void {
    $request = Request::create('/', 'GET', [
        'q' => '',
        'name' => '',
        'phone' => '',
        'email_domain' => '',
        'cursor' => '',
    ]);

    $data = app(SearchContactsDataFromRequest::class)->assemble($request);

    expect($data->q)->toBeNull()
        ->and($data->name)->toBeNull()
        ->and($data->phone)->toBeNull()
        ->and($data->emailDomain)->toBeNull()
        ->and($data->cursor)->toBeNull();
});

it('rejects a phone filter that has no digits', function (): void {
    expect(fn () => app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['phone' => 'abc'])))
        ->toFailValidationOn('phone');
});

it('rejects an oversized page size', function (): void {
    app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['per_page' => 500]));
})->throws(ValidationException::class);
