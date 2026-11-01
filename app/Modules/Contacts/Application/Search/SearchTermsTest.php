<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Search\SearchTerms;

it('cleans a name', function (
    string $raw,
    string $cleaned,
): void {
    expect(SearchTerms::cleanName($raw))->toBe($cleaned);
})->with([
    'hyphen' => ['mary-jane', 'mary jane'],
    'leading quote' => ['"jane', 'jane'],
    'operators' => ['+a -b*', 'a b'],
    'whitespace' => ["  jane \t  smith ", 'jane smith'],
    'only operators' => ['+-<>()~*"@', ''],
]);

it('decides whether a cleaned name can use fulltext', function (
    string $cleaned,
    bool $expected,
): void {
    expect(SearchTerms::isFullTextName($cleaned))->toBe($expected);
})->with([
    'single characters' => ['a b', false],
    'two characters' => ['jo', true],
    'one long word' => ['a bc', true],
    'multibyte pair' => ['éa', true],
    'empty' => ['', false],
]);

it('reduces a phone to searchable digits', function (
    string $raw,
    ?string $digits,
): void {
    expect(SearchTerms::phoneDigits($raw))->toBe($digits);
})->with([
    'local format' => ['0412 345 678', '412345678'],
    'international' => ['+61 4', '614'],
    'one digit' => ['5', null],
    'a lone zero' => ['0', null],
    'zero then one digit' => ['05', null],
    'no digits' => ['abc', null],
]);

it('extracts a domain prefix', function (
    string $raw,
    ?string $prefix,
): void {
    expect(SearchTerms::domainPrefix($raw))->toBe($prefix);
})->with([
    'leading at' => ['@Rex.COM', 'rex.com'],
    'full address' => ['jo@rex.com.au', 'rex.com.au'],
    'only at' => ['@', null],
    'plain word' => ['rex', 'rex'],
    'operator only' => ['-', null],
    'wildcard' => ['*', null],
]);
