# Search and paging at scale: design

- **Date:** 2026-11-01
- **Status:** draft, awaiting approval
- **Scope:** a follow-up to the contacts module and SPA specs. It makes search and listing production-ready on MySQL 8 at millions of contacts, and adds a single search box.

## Intent

A database-performance review (`review-db-performance`, checked with `EXPLAIN` on MySQL 8.4) found that today's search can't scale:
- Name and phone filters use a leading-wildcard `LIKE` (and `lower(name)`), so every search scans the whole table.
- The list runs a `COUNT(*)` on every page and uses deep `OFFSET` paging.
- Idempotency keys live in the database cache.

Rob wants the module deployable to production, so these get fixed rather than documented. Rob also wants the SPA to have **one search box** for name, phone or email domain, instead of three fields.

## Goals

1. Every search and list query uses an index at scale. No full scans, and no `COUNT(*)` per page.
2. One search parameter, `q`, matches a contact by name, phone or email domain.
3. The SPA has a single search box, and its paging scales.
4. Idempotency keys no longer write to the database.
5. Tests stay isolated and centrally managed.

## Non-goals

- A search engine (Scout, Meilisearch). The brief limits backend core components to core Laravel.
- Relevance ranking. Results stay ordered by name, then id.
- Searching email addresses other than by domain.
- Keeping SQLite or Postgres working. The project now targets MySQL 8 only, and MySQL-specific features are allowed.

## Rob's decisions

1. **Name search uses MySQL FULLTEXT** (option 2), not a search-terms table or a prefix-only match.
2. **Phone search keeps "contains"**, so the last few digits still match. Rob's approval of the ngram approach covers this.
3. **Paging becomes cursor-based.** It returns no `total` and no `last_page`.
4. **Idempotency keys move to Redis.**
5. **Search uses a single box** for name, phone or email domain.
6. **The test trade-off:** a second, centrally bound base test case for the full-text search tests is an acceptable exception to "one base test case".

## Design

### Indexes (one migration, MySQL)

- **`contacts.name`:** a FULLTEXT index using the `ngram` parser.
- **`contact_phones.phone`:** a FULLTEXT index using the `ngram` parser.
- **Stopwords:** the migration runs `SET SESSION innodb_ft_enable_stopword = OFF` before creating the indexes and restores the setting afterwards. With stopwords on, the `ngram` parser drops tokens such as `an` in "Jane".
- **`ngram_token_size`:** keep the MySQL default of 2, so search terms need at least 2 characters to use the index.
- **Existing indexes:** keep the `contacts_name_index` B-tree for ordering and the one-character fallback, and keep `contact_emails.domain`.
- **Implementation:** use raw `DB::statement` where Blueprint can't express `WITH PARSER ngram`. Include a reversible `down()`.

### Matching rules

| Input | Rule | Index used |
|---|---|---|
| **Name** (FULLTEXT) | Replace the FULLTEXT boolean operators `+ - < > ( ) ~ * " @` **with spaces**, not deleting them, so "mary-jane" becomes "mary jane" and still matches "Mary-Jane". Collapse whitespace. If no word in the result has 2 or more characters (for example "a b"), use the one-character fallback with the whole cleaned string instead. Search the result as one quoted phrase with `whereFullText('name', '"…"', ['mode' => 'boolean'])`. Substring semantics: "smi" and "mit" both find "Jane Smith". | FULLTEXT |
| Name, 1 character after cleaning | `name LIKE 'x%'` (case-insensitive under the column's collation; no `lower()`) | `contacts_name_index` |
| Name, empty after cleaning | the filter is ignored | none |
| **Phone** (FULLTEXT) | Strip everything except digits, then drop one leading trunk `0` if more than one digit remains. Search the digits as a quoted phrase against `contact_phones.phone`. Contains semantics: "5678", "412 345" and "0412 345 678" all work. | FULLTEXT |
| Phone with fewer than 2 digits after cleaning | the filter is ignored | none |
| **Email domain** in `q` | Lowercase, strip a leading `@`. If the value contains `@`, take the part after the last `@`. Match `domain LIKE 'x%'` (prefix): "rex" finds `rex.com.au` but not `notrex.com.au`. | `contact_emails_domain_index` |
| **`email_domain` filter** (explicit) | Stays an exact match. | `contact_emails_domain_index` |

### Search parameters

- **New `q` parameter:** a contact matches when its **name, any phone, or any email domain** matches `q` under the rules above. It's built as `whereIn('contacts.id', <UNION of three indexed id subqueries>)`, so each branch uses its own index; MySQL won't use the indexes across a plain `OR`.
- **Branches that don't apply:** a branch whose rule says "ignored" (for example the phone branch for `q = "smith"`, which has no digits) is left out of the union. If no branch remains (`q` is `-`, `@` or whitespace), `q` is ignored, the same as an empty filter.
- **Explicit filters stay** (`name`, `phone`, `email_domain`), each using the same per-type rule and combined with AND. Each is a `whereIn('contacts.id', <subquery>)`, replacing `whereHas`.
- **Validation** is updated:
  - `q`: `nullable|string|max:255`;
  - `phone`: keeps its loose raw-input check;
  - `page` is removed, and `cursor` is added (`nullable|string|max:512`).
- **All search logic lives in Application.** Keep `SearchContactsData` and `SearchContacts` as the single place for search, extended with `q` and `cursor` and with `page` removed. Move the matching rules into a small, focused class, for example `Application/Search/ContactSearchQuery`, which builds the subqueries. Keep the controller and CLI thin.

### Cursor pagination

- **Query:** `cursorPaginate(perPage, cursor: …)` ordered by `name`, then `id`. `per_page` keeps its default of 25 and maximum of 100.
- **Response shape** (Laravel's standard cursor-paginated resource collection):
  - `data`;
  - `links`: `first`, `last`, `prev`, `next`;
  - `meta`: `path`, `per_page`, `next_cursor`, `prev_cursor`.

  There is no `total`, `current_page` or `last_page`.
- **Invalid cursor:** an invalid or tampered cursor gives a `validation_failed` 422 on `cursor`, never a 500, and never a silent first page.
  - Validate the cursor explicitly with a rule that decodes it (`Cursor::fromEncoded`). It must decode, and contain exactly the parameters `name` and `id` as strings, plus Laravel's direction flag.
- **OpenAPI:** `ContactPage` and the list parameters are updated to match, and the contract tests keep passing.

### CLI

- `contacts:search {q?} {--name=} {--phone=} {--domain=} {--per-page=25} {--cursor=} {--json}`
- It prints the table, then `Next page: --cursor=<next_cursor>` when there is one.
- `--json` output includes `data` plus `next_cursor` and `prev_cursor`.

### Idempotency in Redis

- `.env.example` sets `CACHE_STORE=redis`, using Laravel's built-in phpredis client. No new package.
- Confirm the phpredis extension is available in the Lerd PHP container.
- Local `.env` (uncommitted) is pointed at Lerd's Redis. Tests keep the `array` store from `phpunit.xml`.
- `EnsureIdempotency` and its lock work unchanged against any store that supports locks.

### SPA

- **Search box:** one input, "Search by name, phone or email domain", bound to `?q=` with the existing 300 ms debounce, replace-history behaviour, and the change-resets-paging rule.
- **Domain badge:** clicking a domain badge sets the exact `email_domain` filter, shown as a removable chip next to the box. Clear removes everything.
- **Paging:** Previous and Next use `?cursor=` in the URL. Any filter change drops `cursor`.
- **Removed:** the "Showing a–b of N" text, the header count and the jump-to-last-page logic, since there's no total any more. The header subtitle becomes neutral ("Search by name, phone or email domain").
- **API layer:** the API types are regenerated from `openapi.json`, and `useContacts`, the query keys and `useContactSearchParams` are updated.
- **Errors:** a 422 on `q` or `cursor` shows under the box, using the field-error pattern from the final fix wave.

### Tests

- **Isolation for full-text search tests (revised).** InnoDB only updates FULLTEXT indexes on commit, so a rolled-back transaction can't see its own rows. Pest can't bind two test cases to the same files, so instead of a second test case class, the **base `TestCase` handles it centrally**:
  - Tests marked with the Pest group `fulltext` (`->group('fulltext')` on the test or `describe`) skip the per-test transaction.
  - Before and after each of those tests, the base case truncates the contacts tables (`contacts`, `contact_emails`, `contact_phones`) and `users`. Nothing is left over for other tests.
  - Only the search tests that hit the FULLTEXT path use the group:
    - `SearchContacts`;
    - the HTTP list endpoint search tests;
    - the search command.
  - Individual test files still never import or `uses()` a database trait. The suite doesn't run in parallel.
- **Rule update:** `.claude/rules/testing.md` gets a short exception explaining the `fulltext` group and why.
- **Search tests prove:**
  - substring name ("mit" finds "Smith"), a one-character name prefix, and operator characters treated as text;
  - phone contains, by last digits and by local `0412…`;
  - domain prefix in `q` versus the exact `email_domain` filter;
  - `q` matching across all three kinds;
  - filters combined with AND;
  - cursor paging through all 40 seeded contacts with no duplicates or gaps;
  - an invalid cursor gives a 422.
- **Index proof:** a Pest test asserts that `EXPLAIN` of each search **id subquery** uses its index:
  - name: the FULLTEXT index (`type` = `fulltext`);
  - phone: the FULLTEXT index;
  - domain prefix: `contact_emails_domain_index` with a range scan.

  These subqueries are where the index choice matters. The outer list query (`whereIn` plus `ORDER BY name, id LIMIT`) is left to the optimizer, which on 40 rows proves little.
- **Known limit:** a very broad term (for example "an") matches a large set that then needs a sort. That's accepted, and bounded by the page size.

## Calls made without Rob's explicit input

- The ngram parser at the default token size of 2, with stopwords disabled at index creation.
- One-character names fall back to a prefix `LIKE`; a one-digit phone filter is ignored.
- Email domain matching is a prefix in `q`, and exact for the explicit filter.
- The explicit filters stay alongside `q`, for third-party API consumers.
- An invalid cursor gives a 422 on `cursor`.
- The `EXPLAIN`-based index test.
- The CLI's `--cursor` option and its "next page" hint.
- In names, FULLTEXT operators become spaces, not deletions. Single-character words fall back to a prefix `LIKE`.
- A `q` branch that doesn't apply is left out of the union. If none applies, `q` is ignored.
- An invalid cursor is a strict 422, using an explicit decoding rule.
- Full-text test isolation is handled by a `fulltext` Pest group in the base `TestCase` (skip the transaction, truncate before and after), not a second test case class, because Pest can't bind two cases to the same files.
- The `EXPLAIN` assertions target the id subqueries.

## Left to the builder

- The exact class name and shape for the search query builder in Application.
- How the SPA chip for the exact domain filter looks, using shadcn defaults.
