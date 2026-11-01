<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

it('strips non-digits from the phone and lowercases the domain', function (): void {
    $request = Request::create('/', 'GET', ['phone' => '+61 412', 'email_domain' => 'REX.com.au']);

    $data = app(SearchContactsDataFromRequest::class)->assemble($request);

    expect($data->phoneDigits)->toBe('61412')
        ->and($data->emailDomain)->toBe('rex.com.au')
        ->and($data->page)->toBe(1)
        ->and($data->perPage)->toBe(25);
});

it('treats blank filters as absent', function (): void {
    $request = Request::create('/', 'GET', ['name' => '', 'phone' => '', 'email_domain' => '']);

    $data = app(SearchContactsDataFromRequest::class)->assemble($request);

    expect($data->name)->toBeNull()
        ->and($data->phoneDigits)->toBeNull()
        ->and($data->emailDomain)->toBeNull();
});

it('rejects a phone filter that has no digits', function (): void {
    try {
        app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['phone' => 'abc']));
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('phone');

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('strips formatting from the phone', function (): void {
    $data = app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['phone' => '(04) 1234-5678']));

    expect($data->phoneDigits)->toBe('0412345678');
});

it('rejects an oversized page size', function (): void {
    app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['per_page' => 500]));
})->throws(ValidationException::class);
