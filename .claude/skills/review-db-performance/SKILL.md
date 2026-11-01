---
name: review-db-performance
description: Use when reviewing Laravel code that queries or writes the database (Actions, queries, migrations, seeders, resources, jobs) for performance at scale, before committing, or when asked for a database performance review. Targets MySQL 8.
argument-hint: "[files or diff range]"
---

# Database performance review

## Scope

Review `$ARGUMENTS` (files or a diff range). If empty, review `git diff main` (branch commits plus uncommitted tracked changes) and the untracked files from `git ls-files --others --exclude-standard`, reading those files in full. Read the migrations for every table the changed code touches before judging a query.

For Laravel specifics, apply `.claude/skills/laravel-best-practices/rules/db-performance.md` and `advanced-queries.md`. Do not repeat their content here; cite them in findings.

Assume tables grow to millions of rows and the API is called by third parties in loops. Where you can, prove a finding instead of guessing:
- use `EXPLAIN` through a read-only database query;
- or count queries in a test with `DB::enableQueryLog()`.

## Queries

- **N+1:** a relationship accessed in a loop, an API Resource or a CLI table without eager loading. Check every path a list endpoint renders. `Model::preventLazyLoading()` only catches this outside production.
- **Unbounded reads:** `->get()`, `->all()` or `->pluck()` with no limit on a table that grows. Use pagination, `chunkById()` or `lazyById()` for batch work, and never `chunk()` while mutating the column you order by.
- **Selecting more than needed:** wide `select *` where a list needs a few columns, or loading models just to count, check existence or read one value. Use `count()`, `exists()` or `value()`.
- **Pagination cost:**
  - A deep `OFFSET` scans every skipped row. Consider `cursorPaginate()` for large or infinite lists.
  - `paginate()` runs a `COUNT(*)`. Use `simplePaginate()` where the total isn't shown.
- **Index use (MySQL):**
  - `WHERE` and `ORDER BY` columns must be covered by an index, including the sort column for every paginated list.
  - A leading-wildcard `LIKE '%x%'` and functions on columns (`lower(col)`, `DATE(col)`) cannot use a B-tree index. Flag them on large tables and name the alternative: a prefix match, a generated or normalised column with an index, `FULLTEXT` (ngram), or a search engine.
  - A composite index is only used left to right. A unique `(contact_id, phone)` index doesn't help a lookup by `phone` alone.
- **`whereHas` / `orWhereHas`:** these become correlated `EXISTS` subqueries. On big parent tables, compare them with a join or an `IN` subquery (see `advanced-queries.md`). Several `whereHas` calls on one query multiply the cost.
- **Ordering:** a sort on a non-indexed or computed expression forces a filesort. Ties need a unique tiebreaker (for example `id`) for stable pagination.

## Writes and transactions

- **Short transactions.** Never put network calls, mail or queue dispatches inside `DB::transaction`. Use `afterCommit` for dispatches.
- **Locking:**
  - `lockForUpdate()` must be inside a transaction and target rows by primary or unique key, so InnoDB locks rows rather than gaps or ranges.
  - Consistent lock order across code paths avoids deadlocks.
  - Retries (`attempts`) only on the outermost transaction.
- **Per-row loops:** a write per row in a loop should be a batch `insert()`, `upsert()` or `insertOrIgnore()`, or a single `update()` / `delete()` with a `whereIn`.
- **Deletes:**
  - A cascade from a large parent in one request is fine for small child sets. Flag it where children are unbounded.
  - Soft deletes need the `deleted_at` column covered in indexes used by default scopes.

## Schema and migrations

- **Foreign keys** are indexed. MySQL does this automatically for FK constraints, but plain `*_id` columns without a constraint are not.
- **Column types** are as narrow as the data. A UUID primary key as `char(36)` costs index space; note it if the table is hot, but don't flag it for this project's established `HasUuids` choice.
- **utf8mb4 limits.** Indexed string lengths stay within the InnoDB key limit (3072 bytes, which is 4 bytes per character with utf8mb4).
- **Large tables.** A migration that adds an index or a non-null column to a large table needs a note on locking and online DDL (`ALGORITHM=INPLACE` / `INSTANT`).
- **Seeders** use batch writes or the domain Action deliberately, and never run per-row queries in a loop on large data without reason.

## Do not flag

- Tables that are bounded by design: per-contact emails and phones, capped at 10 each.
- Premature caching. Recommend a cache only with a stated hot path and an invalidation story.
- Query count in tests or seeders, unless the same pattern ships in a request path.
- Micro-optimisations without a measurable effect at the stated scale.

## Output

One line per finding:

`file:line — severity (high|medium|low) — problem — suggested fix`

Severity reflects the cost at scale: **high** is a full scan, N+1 or unbounded read on a request path; **medium** is an avoidable extra query or a missing index on a growing table; **low** is everything else.

If there is nothing to report, output `No findings`. Do not pad the list with style opinions.
