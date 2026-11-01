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

it('builds the fulltext query from the words ngram can index', function (
    string $cleaned,
    ?string $expected,
): void {
    expect(SearchTerms::nameFullTextQuery($cleaned))->toBe($expected);
})->with([
    'single characters' => ['a b', null],
    'two characters' => ['jo', '+"jo"'],
    'adjacent words as one phrase' => ['mary jane', '+"mary jane"'],
    'trailing initial dropped' => ['chloe s', '+"chloe"'],
    'leading initial dropped' => ['e smith', '+"smith"'],
    'apostrophe splits like ngram' => ["o'reilly", '+"reilly"'],
    'apostrophe only' => ["o'", null],
    'ascii punctuation joins a phrase' => ['jean.luc', '+"jean luc"'],
    'underscore stays in the word' => ['smith_jones', '+"smith_jones"'],
    'initial between words splits the phrase' => ['a bc d ef', '+"bc" +"ef"'],
    'multibyte pair' => ['éa', '+"éa"'],
    'accented words' => ['josé ortiz', '+"josé ortiz"'],
    'empty' => ['', null],
]);

it('knows when the cleaned text is one word to ngram', function (
    string $cleaned,
    bool $expected,
): void {
    expect(SearchTerms::isOneNgramWord($cleaned))->toBe($expected);
})->with([
    'one word' => ['smith', true],
    'underscore stays in the word' => ['smith_jones', true],
    'accented word' => ['josé', true],
    'two words' => ['mary jane', false],
    'apostrophe' => ["o'reilly", false],
    'ascii punctuation' => ['jean.luc', false],
]);

it('accepts q as a phone only when it looks like one', function (
    string $q,
    bool $expected,
): void {
    expect(SearchTerms::looksLikePhone($q))->toBe($expected);
})->with([
    'local format' => ['0412 345', true],
    'international' => ['+61 412', true],
    'padded' => ['  0412  ', true],
    'brackets' => ['(02) 9876', true],
    'one digit' => ['5', false],
    'words and digits' => ['acme 2024', false],
    'name' => ['smith', false],
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
