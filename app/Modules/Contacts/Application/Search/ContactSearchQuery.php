<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Search;

use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class ContactSearchQuery
{
    /**
     * Not a backslash: MySQL string literals treat it as an escape, so it is not portable.
     */
    private const string LIKE_ESCAPE = '!';

    /**
     * Each FULLTEXT match set (q, name, phone) is a distinct derived table joined on the contact id, so
     * the small match sets drive the query. As IN subqueries MySQL either scans contacts and probes the
     * matches per row, or plans a dependent subquery or a FirstMatch semijoin, which evaluates MATCH per
     * row and drops rows. The exact email domain is a B-tree lookup and stays a semijoin. A column list
     * the caller already chose is kept; otherwise only the contact columns are selected.
     *
     * @param  Builder<Contact>  $contacts
     * @return Builder<Contact>
     */
    public function apply(
        Builder $contacts,
        ?string $q,
        ?string $name,
        ?string $phone,
        ?string $emailDomain,
    ): Builder {
        $matchSets = array_filter([
            'search_matches' => $q === null ? null : $this->searchBranches($q),
            'name_matches' => $name === null ? null : $this->nameBranch($name),
            'phone_matches' => $phone === null ? null : $this->phoneBranch($phone),
        ]);

        if ($matchSets !== [] && $contacts->getQuery()->columns === null) {
            $contacts->select('contacts.*');
        }

        foreach ($matchSets as $alias => $matches) {
            $contacts->joinSub($matches->distinct(), $alias, "{$alias}.contact_id", '=', 'contacts.id');
        }

        if ($emailDomain !== null) {
            $contacts->whereIn('contacts.id', $this->domainExactBranch($emailDomain));
        }

        return $contacts;
    }

    /**
     * The union of the q branches that apply, or null when nothing in q is searchable. The phone branch
     * only applies when q reads as a phone number, so "acme 2024" does not match phones containing 2024.
     */
    private function searchBranches(string $q): ?QueryBuilder
    {
        $branches = array_values(array_filter([
            $this->nameBranch($q),
            SearchTerms::looksLikePhone($q) ? $this->phoneBranch($q) : null,
            $this->domainPrefixBranch($q),
        ]));

        if ($branches === []) {
            return null;
        }

        $union = array_shift($branches);

        foreach ($branches as $branch) {
            $union->union($branch);
        }

        return $union;
    }

    /**
     * Contact ids whose name contains the cleaned text, or starts with it when no word has two characters.
     * The FULLTEXT query finds the candidates through the index; the LIKE over those candidates restores
     * the exact substring, including any one-character word ngram cannot match. In that LIKE a space in
     * the cleaned text stands for any one separator, so "mary-jane" still finds "Mary-Jane".
     */
    public function nameBranch(string $raw): ?QueryBuilder
    {
        $cleaned = SearchTerms::cleanName($raw);

        if ($cleaned === '') {
            return null;
        }

        $branch = Contact::query()->select('contacts.id as contact_id')->toBase();
        $fullText = SearchTerms::nameFullTextQuery($cleaned);

        if ($fullText === null) {
            return $branch->whereRaw('contacts.name like ? escape ?', [$this->escapeLike($cleaned).'%', self::LIKE_ESCAPE]);
        }

        $separatorsAsWildcards = str_replace(' ', '_', $this->escapeLike($cleaned));

        return $branch
            ->whereFullText('contacts.name', $fullText, ['mode' => 'boolean'])
            ->whereRaw('contacts.name like ? escape ?', ['%'.$separatorsAsWildcards.'%', self::LIKE_ESCAPE]);
    }

    /**
     * Contact ids by a FULLTEXT phrase over the phone digits.
     */
    public function phoneBranch(string $raw): ?QueryBuilder
    {
        $digits = SearchTerms::phoneDigits($raw);

        if ($digits === null) {
            return null;
        }

        return ContactPhone::query()->select('contact_phones.contact_id as contact_id')->toBase()
            ->whereFullText('contact_phones.phone', '"'.$digits.'"', ['mode' => 'boolean']);
    }

    /**
     * Contact ids whose email domain starts with the text.
     */
    public function domainPrefixBranch(string $raw): ?QueryBuilder
    {
        $prefix = SearchTerms::domainPrefix($raw);

        if ($prefix === null) {
            return null;
        }

        return ContactEmail::query()->select('contact_emails.contact_id as contact_id')->toBase()
            ->whereRaw('contact_emails.domain like ? escape ?', [$this->escapeLike($prefix).'%', self::LIKE_ESCAPE]);
    }

    /**
     * Contact ids whose email domain equals the text.
     */
    public function domainExactBranch(string $domain): QueryBuilder
    {
        return ContactEmail::query()->select('contact_emails.contact_id as contact_id')->toBase()
            ->where('contact_emails.domain', $domain);
    }

    private function escapeLike(string $value): string
    {
        return preg_replace('/[!%_]/', self::LIKE_ESCAPE.'$0', $value) ?? $value;
    }
}
