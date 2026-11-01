# Contacts module, API and CLI: design

- **Date:** 2026-11-01
- **Status:** draft, awaiting approval
- **Scope:** part 1 of the Rex technical exercise, covering the backend module, HTTP API, CLI, auth endpoints and the OpenAPI contract. Part 2 (frontend setup, then the SPA) and the README come later, against this contract.

## Intent

Rex wants a contacts domain module in a Laravel modular monolith, reachable over an HTTP API and a CLI. Contacts must be upserted, deleted, searched (by name, phone or email domain), read and called (a mocked call). Phones and emails must be valid, and phones must be E.164 and Australian or New Zealand only.

Rex's teams care about four things:
- business logic reused synchronously, asynchronously and across interfaces;
- several resources changed in one transaction per request;
- business rules that are tested;
- error messages that are as informative as possible for third-party API consumers.

The code doesn't have to run. Rex says they're "more interested in patterns". It must still be code you'd deploy, with strong types.

What Rob wants reviewers to notice first is **the architecture**: a feature module with clear layers and thin entry points, where one Action serves HTTP, CLI and queue alike.

## Goals

1. A `Contacts` module under `app/Modules/Contacts/` with Domain, Application and Presentation layers, as the project rules describe.
2. Every use case (upsert, delete, search, read, call) is one Action, reachable from both HTTP and CLI. At least one use case is also reachable asynchronously through a queued job.
3. **Atomic writes:** a contact and its emails and phones change together or not at all.
4. **Idempotent endpoints:** repeating any request leaves the same state and has no extra side effects.
5. Validation rules defined once and shared by HTTP and CLI, with informative, field-level errors in one consistent format.
6. A hand-written OpenAPI document that is the contract the frontend builds against.
7. Session-authenticated API with login, logout and current-user endpoints. The CLI needs no auth.
8. Business rules covered by colocated Pest tests.

## Non-goals

- External or third-party API authentication (tokens, OAuth). This is out of scope, per Rob.
- Per-user or per-team authorisation rules. Any logged-in user can manage every contact.
- A real telephony provider, and persisting a call history.
- The frontend and the README, which are part 2.
- Bulk import or export, contact merging and soft deletes.

## Anti-goals

- Business logic in controllers, commands or jobs.
- Validation rules duplicated between HTTP and CLI.
- Write paths that delete and recreate child rows, which breaks idempotency and churns IDs.
- Error responses that leak internals: stack traces, SQL or class names.
- Packages outside core Laravel for core components (brief constraint), and new dependencies without Rob's approval (project rule).

## Constraints

- Laravel 13, PHP 8.5, Pest 5. The database is SQLite locally and in tests, so the code must stay portable.
- The project rules in `.claude/rules/` are binding:
  - `architecture.md`: layers, entry-point flow, DTOs and transactions, a provider in `bootstrap/providers.php`;
  - `testing.md`: colocated `*Test.php` files, `RefreshDatabase` in the base `TestCase`, no database traits in tests;
  - `commits.md` and `package-management.md`.
- The arch tests in `tests/Arch` already enforce that Domain doesn't use Application or Presentation, and that Application doesn't use Presentation.
- Branch `REX-0001`; all of this goes into the same PR.

## Rob's decisions

1. **Module layout:**

   ```
   app/Modules/Contacts/
   ├── Application/   Actions/, Assemblers/, DTOs/, Validation/
   ├── Domain/        Models/ (Contact, ContactEmail, ContactPhone)
   └── Presentation/  Http/Controllers/, Console/Commands/
   ```

2. **One shared validator per use case,** used by HTTP and CLI alike.
3. **Assemblers turn raw input into DTOs** and are named after the input source: `ContactDataFromRequest`, `ContactDataFromCli`.
4. **Atomic and idempotent writes.** Concretely:
   - a database unique constraint on (contact, email) and on (contact, phone);
   - values normalised before they are stored;
   - child rows synced rather than deleted and recreated: insert the missing ones, leave unchanged ones alone, remove the ones no longer submitted;
   - the whole write in one transaction, with the contact row locked against concurrent updates;
   - a concurrency test if time permits.
5. **Calls are mocked.** The outcomes are an enum, and the mock picks one at random.
6. **Auth is Laravel's default session auth,** with login built for the SPA. External API auth is out of scope.
7. **An OpenAPI document is the API contract** for the frontend.
8. **Order of work:** backend and CLI first, then the frontend.
9. **Phone rule:** E.164, AU (`+61`) or NZ (`+64`) only. Rob's example rule was `^\+(61|64)[1-9][0-9]{7,9}$`.
10. **Required fields:** a contact has a `name` (required, max 255), at least one email and at least one phone. This follows Rob's example validator.

## Design

### Domain (`Domain/`)

- **Models** (Eloquent; storing them in Domain is the documented concession):
  - `Contact` has a UUID primary key (`HasUuids`) and a `name`.
  - `ContactEmail` has `contact_id`, `email` (normalised) and `domain` (derived on save, indexed).
  - `ContactPhone` has `contact_id` and `phone` (E.164).
  - Children cascade-delete with their contact.
  - Unique indexes on (`contact_id`, `email`) and (`contact_id`, `phone`).
- **Value objects:**
  - `PhoneNumber` is a `final readonly` value object. Input is trimmed but never reformatted: the brief says numbers *must* be E.164, so `0412 345 678` or `+61 412 345 678` is rejected with a message that names the expected format. The accepted patterns are exactly:
    - AU: `^\+61[2-478]\d{8}$`, which is 9 digits after `+61`;
    - NZ: `^\+64[2-9]\d{7,9}$`, which is 8–10 digits after `+64`.

    Anything else raises a domain exception.
  - `EmailAddress` is a `final readonly` value object. It normalises by trimming and lowercasing, and exposes `domain()`.

  The validator uses the same rules, so they're defined in exactly one place.
- **Calls** live inside the Contacts module at `app/Modules/Contacts/Domain/Telephony/`. There is no separate Telephony module; that's out of scope.
  - `CallOutcome` is a backed enum: `Answered`, `NoAnswer`, `Busy`, `Voicemail`, `Rejected`, `Failed`.
  - `TelephonyGateway` is the port: `place(PhoneNumber $to): CallOutcome`.

### Application (`Application/`)

- **Actions** (each one public method with typed input and output):

  | Action | Input | Output |
  |---|---|---|
  | `UpsertContact` | `UpsertContactData` | `UpsertResult` (contact plus a `created` flag) |
  | `GetContact` | contact ID | `Contact` |
  | `SearchContacts` | `SearchContactsData` | paginator of `Contact` |
  | `DeleteContact` | contact ID | void |
  | `CallContact` | `CallContactData` | `CallResult` (outcome, number dialled, timestamp) |

  Missing contacts raise a module exception, `ContactNotFound`, which the HTTP and CLI layers map to their own error output.
- **How `UpsertContact` writes:**
  1. Open one `DB::transaction`.
  2. Get the row with Laravel's `createOrFirst` on the ID. It handles two concurrent first creates of the same UUID by catching the unique violation and reading the winner's row.
  3. Lock the row (`lockForUpdate`; a no-op on SQLite, effective on MySQL and Postgres).
  4. Update the name.
  5. Sync emails and phones (see Rob's decision 4).
  6. Return the contact with its emails and phones loaded.

  `created` is true only for the request that actually inserted the row. Repeating the same input changes nothing.
- **Duplicates in the input** are de-duplicated silently after normalisation, which suits idempotency; they are not a validation error. The cap of 10 emails and 10 phones counts the submitted items, before de-duplication.
- **Which number `CallContact` dials:** the optional `phone` must be one of the contact's stored phones. If it isn't, validation fails on `phone` with a message saying so, so the endpoint can never dial an arbitrary number. With no `phone`, it dials the contact's earliest-added phone (lowest ID).
- **DTOs** are `final readonly` classes: `UpsertContactData`, `SearchContactsData`, `CallContactData`. They carry value objects and typed properties, never raw arrays.
- **Validation:** one validator class per use case that has input:
  - `ContactValidator` for upsert;
  - a search validator for the filters and paging;
  - a call validator for the optional phone.

  Each returns validated data, or throws Laravel's `ValidationException` with field-level messages.
- **Assemblers:** `ContactDataFromRequest` and `ContactDataFromCli`. They are the only places that know where input came from.
- **Async:** the `CallContactJob` queued job wraps `CallContact`. This shows the third way in, alongside HTTP and CLI.
  - A job is an entry point like a controller or command, so it lives in `Presentation/Queue/CallContactJob.php`, and the project's architecture rule applies to it.
  - It logs the outcome.
  - On a `Failed` outcome it throws, so the queue retries it: 3 tries with backoff. Nothing is persisted; the README lists call history as future work.

### Infrastructure

`FakeTelephonyGateway` implements `TelephonyGateway` and returns a random `CallOutcome` (Rob's decision 5). It is bound in `ContactsServiceProvider`. Tests bind a fake that returns a fixed outcome, so every outcome path can be tested reliably.

It sits under `app/Modules/Contacts/Infrastructure/`, a fourth folder beyond Rob's tree. Code that adapts the outside world doesn't belong in Domain or Application, and adding the folder makes that boundary visible. Infrastructure may use Domain. Domain and Application must not use Infrastructure. Extend the arch test to cover that.

### Presentation: HTTP (`Presentation/Http/`)

**Routes** are under `/api/v1`, use the `web` middleware stack (session and CSRF) plus `auth`, and are registered by the module's provider:

The routes follow Laravel's RESTful resource conventions (Rob's decision; amended after the review):

| Method and path | Controller method | Action | Success | Notes |
|---|---|---|---|---|
| `GET /api/v1/contacts` | `index` | `SearchContacts` | 200 | query params `name`, `phone`, `email_domain`, `page`, `per_page` |
| `POST /api/v1/contacts` | `store` | `UpsertContact` with a server-generated id | 201 plus a `Location` header | for clients without their own ids; `Idempotency-Key` header required, so a retry can't create a duplicate |
| `GET /api/v1/contacts/{contact}` | `show` | `GetContact` | 200 | 404 if missing |
| `PUT /api/v1/contacts/{contact}` | `update` | `UpsertContact` | 201 if created, 200 if updated | the upsert: `{contact}` is a client-supplied UUID, which makes create-or-replace idempotent. PUT only; there is no PATCH, because there is no partial update |
| `DELETE /api/v1/contacts/{contact}` | `destroy` | `DeleteContact` | 204 | 404 if already gone (same end state) |
| `POST /api/v1/contacts/{contact}/calls` | `CallController@store` | `CallContact` | 200 with the outcome | `Idempotency-Key` header required; outcome `Failed` → 502 |

**Search rules:**
- `name` is a case-insensitive "contains" match.
- `phone` is a digits-only "contains" match on the stored numbers, so "1234" matches the last digits and a full E.164 number matches exactly.
- `email_domain` is an exact, case-insensitive match on the stored domain column.
- Filters combine with AND, and no filters lists all contacts.
- `per_page` defaults to 25, with a maximum of 100.

**Output:** contacts are rendered with Laravel API Resources (`ContactResource`, and its collection with pagination meta).

**Idempotency-Key middleware:**
- It applies to the call endpoint only. PUT and DELETE are idempotent by design.
- It stores the first **2xx** response in the cache, keyed by the user, the route and the key, for 24 hours. A retry with the same key returns the stored response.
- It never stores 4xx or 5xx responses, including the 502 for `Failed`, so a corrected or retried request can still succeed.
- It holds a cache lock while the first request runs.
- The same key with a different request body gets 422, code `idempotency_key_reused`.
- A missing key gets 400, code `idempotency_key_missing`.

**Errors:** every error response uses RFC 9457 problem details (`application/problem+json`):
- the standard fields `type`, `title`, `status` and `detail`;
- a stable machine-readable `code`;
- for validation errors, an `errors` object keyed by field (for example `emails.1`) with human-readable messages.

Codes include:
- `validation_failed`
- `invalid_credentials`
- `contact_not_found`
- `unauthenticated`
- `csrf_token_mismatch`
- `idempotency_key_missing`
- `idempotency_key_reused`
- `telephony_failed`
- `too_many_requests`

Render this in `bootstrap/app.php`'s exception handling for `api/*` requests, which already render JSON. Never include internals.

**Rate limiting:** Laravel's `throttle` middleware on the API, and a stricter limit on login.

### Presentation: CLI (`Presentation/Console/Commands/`)

**Commands:**

| Command | Behaviour |
|---|---|
| `contacts:upsert {id?} --name= --email=* --phone=*` | No `id`: generate a UUID and print it. With an `id`: the same idempotent upsert as PUT |
| `contacts:show {id}` | Read one contact |
| `contacts:search --name= --phone= --domain= --page= --per-page=` | Search |
| `contacts:delete {id}` | Delete |
| `contacts:call {id} --phone= --queue` | `--queue` dispatches `CallContactJob` instead of calling inline |

**Output:** tables by default, and `--json` for machine-readable output.

**Exit codes:**
- validation errors list each field and exit with `Command::INVALID`;
- not found and telephony failure exit with `Command::FAILURE`.

### Auth

This is framework glue rather than domain, so it lives in `app/Http/Controllers/Auth/`, outside the module:
- `POST /login` uses `Auth::attempt`, then regenerates the session. Bad credentials get a 422 problem response with code `invalid_credentials` and one generic message, so it never reveals whether the email exists. Login is JSON-only.
- `POST /logout` invalidates the session and regenerates the CSRF token.
- `GET /api/v1/me` returns the current user's `id`, `name` and `email`.
- The problem-details renderer covers `/login` and `/logout` as well as `api/*`.

The SPA is same-origin: Laravel's `XSRF-TOKEN` cookie is sent back by Axios as `X-XSRF-TOKEN`. Unauthenticated requests get a 401 problem response, not a redirect.

A `DatabaseSeeder` creates a demo user, `demo@example.com` / `password`, for local use only. The README will document it.

### Wiring

`ContactsServiceProvider`, listed in `bootstrap/providers.php`:
- loads the module's routes file;
- registers the commands;
- binds `TelephonyGateway` to `FakeTelephonyGateway`.

Migrations live in `database/migrations` (the Laravel default), named for the module.

### OpenAPI contract

`app/Modules/Contacts/Presentation/Http/openapi.json` is hand-written OpenAPI 3.1. It is JSON so that tests can parse it with no extra dependency; the only YAML parser in the repo comes in indirectly through a dev tool.

It covers every endpoint above, including auth, with:
- request and response schemas;
- the `Idempotency-Key` header;
- the problem-details error schema and its codes;
- the 401, 404, 419, 422, 429 and 502 responses.

A Pest test asserts that every module route appears in the document and every documented path exists, so the contract can't drift.

### Tests

Tests are colocated, with no database traits in individual tests. They cover:

| Area | What the tests check |
|---|---|
| Value objects | AU and NZ numbers accepted; others rejected, including wrong country, non-E.164 and too short or long; email normalisation and domain |
| `UpsertContact` | create; update; sync adds and removes children; a repeated call is a no-op, with the same IDs and no extra rows; duplicate inputs after normalisation stored once |
| `SearchContacts` | each filter; filters combined; pagination cap |
| `DeleteContact` | delete; a second delete raises `ContactNotFound` |
| `CallContact` | each outcome via the fixed fake |
| Middleware | idempotency replay, key reuse and missing key |
| HTTP | each endpoint's status codes and problem-details bodies, including 401 when logged out |
| CLI | each command's output and exit codes |
| Contract | OpenAPI route parity |
| Concurrency (if time permits) | two concurrent upserts of the same contact end in one consistent state |

## Calls made without Rob's explicit input

Rob approved most of these in chat as guesses, but they started as mine:
- `PhoneNumber` and `EmailAddress` value objects.
- AU numbers tightened to 9 digits after `+61`, and NZ to 8–10 after `+64`. This is stricter than Rob's example regex; revert if he prefers his.
- Versioned `/api/v1` paths; PUT with a client-supplied UUID for idempotent create; 404 on a repeated delete.
- The `Idempotency-Key` header (required) on calls, stored with Laravel's cache.
- `CallOutcome` cases; 200 for every outcome except `Failed`, which returns 502; calling the first phone when none is given.
- Search rules (contains for name and phone, exact for domain) and page-based pagination capped at 100.
- RFC 9457 problem-details errors with stable codes.
- An `Infrastructure/` folder for the fake gateway, which goes beyond Rob's tree.
- `CallContactJob` plus `contacts:call --queue` as the async demonstration.
- Auth controllers in `app/Http/Controllers/Auth`; a demo-user seeder; any logged-in user manages all contacts.
- An OpenAPI document in JSON, with a route-parity test.
- `--json` output and exit codes for the CLI.
- Caps on input size: at most 10 emails and 10 phones per contact.
- Phones must already be E.164 (trimmed, never reformatted), with the exact AU and NZ patterns above.
- Duplicates in the input are de-duplicated silently, and the cap counts submitted items.
- A call can only dial one of the contact's own phones; the default is the earliest-added one.
- Concurrent first creates are handled with `createOrFirst`; `created` is true only for the request that inserted the row.
- The idempotency cache stores 2xx responses only.
- `CallContactJob` lives in `Presentation/Queue/`, logs the outcome, and retries 3 times on `Failed`. `TelephonyGateway` lives in `app/Modules/Contacts/Domain/Telephony/`, inside the Contacts module; a separate Telephony module is out of scope (Rob).
- Login failures return 422 `invalid_credentials` with a generic message; `/me` returns `id`, `name` and `email`.
- Search results are ordered by name, then ID. Emails are not unique across contacts.

## Left to the builder

- The exact validation messages, but they must name the field and the rule.
- The column types and index names, within the constraints above.
- How the problem-details renderer is structured, as long as it is one place.
- Table layouts for CLI output.
- The OpenAPI wording and examples.
