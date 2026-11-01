# Rex CRM: Contacts module

A Contacts module for a Laravel 13 modular monolith, with a React + TypeScript SPA on top. You can upsert, read, search, delete and call contacts through a JSON API, Artisan commands or a queued job. Calls go to a mocked telephony provider.

## How I used AI

Claude Code wrote most of the code. I spent my time on decisions, review, and a committed setup in `.claude/` that keeps the AI in line:

- **Hooks** block destructive commands, enforce the commit format and lint every edit.
- **Rules** teach it this architecture.
- **Skills** review each commit (DRY, SOLID, OWASP, database performance), write PR descriptions, and scale the process to the size of the task.

Each part went from brainstorm to spec to plan (in `docs/superpowers/`), was built in reviewed waves, and got a final review on the most capable model.

## Concessions and next steps

- **Models live in Domain**, a pragmatic choice rather than strict DDD.
- **Session auth only.** There are no API tokens or per-team permissions.
- **Calls are mocked**, and there's no call history.
- **No concurrency test.** Upserts take real row locks, but no test runs two at once.
- **Search has no totals.** Cursor paging gives Previous and Next but no page count. A very broad term is capped at 2 s and returns a 422 `search_too_broad`.
- **The index migration blocks writes** on `contacts` while it runs, so a large table needs a maintenance window or pt-online-schema-change.

With more time I'd add:

- CI that runs linting and the tests;
- parallel testing;
- more of the Claude setup in `.claude/`;
- an external API with token auth;
- team-level authorisation;
- a real telephony adapter;
- route-level code splitting in the SPA;
- smaller, stacked PRs instead of one large one, so each is easier to review.

## Running it

You need PHP 8.5 (with `redis`), Node 22, pnpm 11, MySQL 8 and Redis, plus `jq` for the Claude hooks. MySQL needs a `rex_code_challenge` database and a `rex_code_challenge_testing` one, and must run with `innodb_ft_enable_stopword=OFF`.

```bash
composer install && pnpm install --frozen-lockfile
cp .env.example .env && php artisan key:generate   # set DB_* for your MySQL
php artisan migrate --seed
pnpm run build   # or: composer run dev
```

Sign in as **demo@example.com / password**. The seeder adds 10,000 contacts, so search and paging run on realistic volume. `php artisan contacts:check-search-indexes` checks the MySQL setup.

- **API contract:** `app/Modules/Contacts/Presentation/Http/openapi.json`
- **CLI:** `php artisan contacts:{upsert,show,search,delete,call}`
- **Tests:** `php artisan test` and `pnpm test`

## Architecture

```
app/Modules/Contacts/
├── Domain/          models, value objects (PhoneNumber, EmailAddress, ContactId), telephony port
├── Application/     Actions, validators, assemblers, readonly DTOs
├── Infrastructure/  fake telephony gateway
└── Presentation/    HTTP, console commands, queued job
```

- **Layers are enforced.** Dependencies point inward, and Pest architecture tests fail if they don't.
- **One Action serves every entry point.** The API, CLI and queued job all go through validator → assembler → DTO → Action.
- **Writes are atomic and idempotent.** Each upsert is one locked transaction. `POST` create and call require an `Idempotency-Key`.
- **Phones are E.164, AU or NZ only**, enforced by the value objects.
- **Errors are RFC 9457** problem details with stable codes.
- **The OpenAPI contract is tested** against the routes, and the SPA's types are generated from it.
- **Search is indexed.** Name and phone use FULLTEXT `ngram` indexes, email domain a prefix index, and tests check the `EXPLAIN` output.
- **The SPA** (React 19, TanStack Query, shadcn/ui) is organised by module and keeps search in the URL.
- **Tests sit next to the code** they test.
