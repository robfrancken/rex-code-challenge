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
     * q is the distinct union of the branches that apply, joined as a derived table so the small match
     * set drives the query. As IN (union) MySQL either scans contacts and probes the matches per row or
     * plans a dependent subquery, which evaluates MATCH per row and returns wrong rows.
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
        $branches = $q === null ? [] : array_values(array_filter([
            $this->nameBranch($q),
            $this->phoneBranch($q),
            $this->domainPrefixBranch($q),
        ]));

        if ($branches !== []) {
            $union = array_shift($branches);

            foreach ($branches as $branch) {
                $union->union($branch);
            }

            $contacts
                ->select('contacts.*')
                ->joinSub($union, 'search_matches', 'search_matches.contact_id', '=', 'contacts.id');
        }

        $filters = [
            $name === null ? null : $this->nameBranch($name),
            $phone === null ? null : $this->phoneBranch($phone),
            $emailDomain === null ? null : $this->domainExactBranch($emailDomain),
        ];

        foreach (array_filter($filters) as $filter) {
            $contacts->whereIn('contacts.id', $filter);
        }

        return $contacts;
    }

    /**
     * Contact ids by name: a FULLTEXT phrase, or a prefix match when no word has two characters.
     */
    public function nameBranch(string $raw): ?QueryBuilder
    {
        $cleaned = SearchTerms::cleanName($raw);

        if ($cleaned === '') {
            return null;
        }

        $branch = Contact::query()->select('contacts.id as contact_id')->toBase();

        if (SearchTerms::isFullTextName($cleaned)) {
            return $branch->whereFullText('contacts.name', '"'.$cleaned.'"', ['mode' => 'boolean']);
        }

        return $branch->whereRaw('contacts.name like ? escape ?', [$this->escapeLike($cleaned).'%', self::LIKE_ESCAPE]);
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
