<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Validation\Rules\ValidContactCursor;
use App\Modules\Contacts\Domain\Models\Contact;
use Database\Factories\Contacts\ContactFactory;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Validator;

function cursorPasses(string $value): bool
{
    return Validator::make(['cursor' => $value], ['cursor' => [new ValidContactCursor]])->passes();
}

/**
 * @param  array<string, mixed>  $parameters
 */
function encodedCursor(array $parameters): string
{
    return (new Cursor($parameters))->encode();
}

it('rejects text that is not a cursor', function (string $value): void {
    expect(cursorPasses($value))->toBeFalse();
})->with([
    'garbage' => 'garbage',
    'base64 of non-json' => base64_encode('not json at all'),
]);

it('rejects a cursor with the wrong parameters', function (array $parameters): void {
    expect(cursorPasses(encodedCursor($parameters)))->toBeFalse();
})->with([
    'extra parameter' => [[
        'name' => 'Jane',
        'id' => 'abc',
        'extra' => 'x',
    ]],
    'array parameter' => [[
        'name' => ['x'],
        'id' => 'abc',
    ]],
    'missing id' => [['name' => 'Jane']],
    'numeric id' => [[
        'name' => 'Jane',
        'id' => 5,
    ]],
]);

it('accepts a cursor issued by the paginator', function (): void {
    ContactFactory::new()->count(3)->create();
    $next = Contact::query()->orderBy('name')->orderBy('id')->cursorPaginate(2)->nextCursor();

    expect(cursorPasses((string) $next?->encode()))->toBeTrue();
});

it('reports the cursor message', function (): void {
    $validator = Validator::make(['cursor' => 'garbage'], ['cursor' => [new ValidContactCursor]]);

    expect($validator->errors()->first('cursor'))->toBe('The cursor is not a valid page cursor.');
});
