<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Search;

final class SearchTerms
{
    /**
     * The loose shape of a typed phone number: an optional +, then digits, spaces, dashes and brackets.
     */
    public const string PHONE_PATTERN = '/^\+?[\s\-(]*[0-9][0-9\s\-()]*$/';

    /**
     * Where the ngram parser breaks words: whitespace and every ASCII character outside [A-Za-z0-9_].
     * Non-ASCII characters always stay inside a word.
     */
    private const string NGRAM_WORD_BREAK = '/[\x00-\x2F\x3A-\x40\x5B-\x5E\x60\x7B-\x7F]+/u';

    /**
     * The boolean-mode FULLTEXT operators, which name search treats as separators.
     *
     * @var list<string>
     */
    private const array OPERATORS = ['+', '-', '<', '>', '(', ')', '~', '*', '"', '@'];

    /**
     * Replaces the boolean-mode operators with spaces so search text is only ever text.
     */
    public static function cleanName(string $raw): string
    {
        $spaced = strtr($raw, array_fill_keys(self::OPERATORS, ' '));

        return trim(preg_replace('/\s+/u', ' ', $spaced) ?? $spaced);
    }

    /**
     * A MySQL regex for one run of what cleanName turns into a single space: whitespace and the operators.
     */
    public static function separatorRunPattern(): string
    {
        return '[\s'.implode('', array_map(fn (string $operator): string => '\\'.$operator, self::OPERATORS)).']+';
    }

    /**
     * Whether the cleaned text is a single word to the ngram parser, so its FULLTEXT phrase alone
     * already matches it exactly as a substring.
     */
    public static function isOneNgramWord(string $cleaned): bool
    {
        return preg_match(self::NGRAM_WORD_BREAK, $cleaned) !== 1;
    }

    /**
     * The boolean-mode FULLTEXT query over the words ngram (size 2) can match, or null when no word
     * has two characters. A one-character word cannot be matched, even inside a phrase, so it is
     * left out and splits the phrase: each run of adjacent longer words becomes one required phrase.
     */
    public static function nameFullTextQuery(string $cleaned): ?string
    {
        $phrases = [];
        $run = [];

        foreach (preg_split(self::NGRAM_WORD_BREAK, $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (mb_strlen($word) >= 2) {
                $run[] = $word;

                continue;
            }

            $phrases[] = $run;
            $run = [];
        }

        $phrases[] = $run;

        $required = array_map(
            fn (array $words): string => '+"'.implode(' ', $words).'"',
            array_filter($phrases),
        );

        return $required === [] ? null : implode(' ', $required);
    }

    /**
     * Whether free text reads as a phone number: the loose phone shape with at least two digits to search.
     */
    public static function looksLikePhone(string $raw): bool
    {
        return preg_match(self::PHONE_PATTERN, trim($raw)) === 1 && self::phoneDigits($raw) !== null;
    }

    /**
     * Digits only, without one trunk-prefix zero, or null when fewer than two digits remain.
     */
    public static function phoneDigits(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', $raw) ?? '';

        if (strlen($digits) > 1 && $digits[0] === '0') {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 2 ? $digits : null;
    }

    /**
     * The lowercase domain part of an address or partial domain; null when it cannot start a domain.
     */
    public static function domainPrefix(string $raw): ?string
    {
        $value = mb_strtolower(trim($raw));
        $value = ltrim($value, '@');

        if (str_contains($value, '@')) {
            $value = substr($value, (int) strrpos($value, '@') + 1);
        }

        return preg_match('/^[\p{L}\p{N}]/u', $value) === 1 ? $value : null;
    }
}
