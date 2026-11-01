<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Search;

final class SearchTerms
{
    /**
     * Replaces the boolean-mode operators with spaces so search text is only ever text.
     */
    public static function cleanName(string $raw): string
    {
        $spaced = strtr($raw, array_fill_keys(['+', '-', '<', '>', '(', ')', '~', '*', '"', '@'], ' '));

        return trim(preg_replace('/\s+/u', ' ', $spaced) ?? $spaced);
    }

    /**
     * FULLTEXT (ngram, size 2) cannot match a word shorter than two characters.
     */
    public static function isFullTextName(string $cleaned): bool
    {
        foreach (explode(' ', $cleaned) as $word) {
            if (mb_strlen($word) >= 2) {
                return true;
            }
        }

        return false;
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
