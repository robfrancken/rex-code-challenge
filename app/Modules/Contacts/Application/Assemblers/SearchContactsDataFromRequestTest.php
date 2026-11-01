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

it('rejects an oversized page size', function (): void {
    app(SearchContactsDataFromRequest::class)->assemble(Request::create('/', 'GET', ['per_page' => 500]));
})->throws(ValidationException::class);
