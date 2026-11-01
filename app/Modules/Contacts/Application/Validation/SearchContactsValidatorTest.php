<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\SearchContactsValidator;
use Illuminate\Validation\ValidationException;

it('accepts valid filters', function (): void {
    $input = ['name' => 'jane', 'phone' => '+6141', 'email_domain' => 'rex.com.au', 'page' => 2, 'per_page' => 100];

    expect(app(SearchContactsValidator::class)->validate($input))->toBe($input);
});

it('rejects invalid filters', function (array $input, string $key): void {
    try {
        app(SearchContactsValidator::class)->validate($input);
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($key);

        return;
    }

    $this->fail('Expected a ValidationException.');
})->with([
    'per_page too large' => [['per_page' => 101], 'per_page'],
    'page zero' => [['page' => 0], 'page'],
    'phone not numeric' => [['phone' => 'abc'], 'phone'],
]);
