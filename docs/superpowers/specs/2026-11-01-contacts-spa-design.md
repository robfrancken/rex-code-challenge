# Contacts SPA: design

- **Date:** 2026-11-01
- **Status:** draft, awaiting approval
- **Scope:** part 2 of the Rex technical exercise. It covers the frontend setup commit, the single-page app (SPA) over the contacts API, and the small backend support it needs. The README is a separate task.

## Intent

The brief asks for "a SPA frontend that can be fluid in scope, but must interact with the majority of these created API endpoints. Be pragmatic and use standard components. No need to change the look and feel."

**Rob's aims:**
- **The reviewer's path:** a Rex reviewer opens the app, sees a login screen, signs in as the seeded demo user, and lands on a list of seeded contacts. From there they can search, view, create, edit, delete and call contacts.
- **The look:** clean and a little polished, using shadcn's defaults rather than a custom theme.
- **Under the hood:** the SPA should show how Rob would structure a large CRUD frontend. That means a clean module layout, typed API access generated from the contract, server state in TanStack Query, and routing with React Router.

## Goals

1. Use every contacts endpoint and every auth endpoint:
   - `GET /api/v1/contacts` (search)
   - `POST /api/v1/contacts`
   - `GET /api/v1/contacts/{contact}`
   - `PUT /api/v1/contacts/{contact}`
   - `DELETE /api/v1/contacts/{contact}`
   - `POST /api/v1/contacts/{contact}/calls`
   - `POST /login`, `POST /logout` and `GET /api/v1/me`
2. Show API errors in the UI, using the API's own problem-details messages. Validation errors appear under the field the API names.
3. Make API access type-safe from the OpenAPI contract, so the SPA cannot drift from the API.
4. Use React Router for real routes: deep links and refresh work, and search terms and paging live in the URL.
5. Put tests next to the code for the parts with logic: the API client, the error mapping, the auth guard and the main screens.

## Non-goals

- A custom visual design or theme. Use shadcn defaults (zinc palette).
- Offline support, optimistic updates and real-time updates.
- Mobile-first layouts. The layout must not break on narrow screens, but the target is desktop.
- End-to-end browser tests.
- Registration, password reset, or any user management.

## Anti-goals

- A frontend that duplicates the server's business rules. The server validates, and the SPA shows its errors.
- Hand-written API types that can drift from `openapi.json`.
- Global client state stores (Redux, Zustand). Server state lives in TanStack Query, and UI state lives in the URL or the components.
- Edits to the generated shadcn components. Keep them as generated, so reviewers can skip them.

## Constraints

- **Repo:** Laravel 13 at the root, Vite 8, Tailwind 4, pnpm 11.
- **Supply chain:** `pnpm-workspace.yaml` sets `minimumReleaseAge: 10080` (7 days), `trustPolicy: no-downgrade`, and `strictDepBuilds`. No package may be added if it can't satisfy these. If the newest version is too young, pin an older one. Never weaken these settings.
- **Dependency approval:** every dependency change asks Rob for approval, through the `ask-dependency-change` hook.
- **The `lint-frontend` hook:** runs `node_modules/.bin/prettier --write` and `node_modules/.bin/eslint --fix` on every `.ts`, `.tsx`, `.js` or `.jsx` edit, and blocks on ESLint errors. The ESLint config must therefore exist, and pass on generated code, before lots of files are written.
- **Project rules:** `.claude/rules/testing.md` (tests next to the code), `commits.md`, `package-management.md`, and the `review-dry`, `review-solid` and `review-owasp-frontend` skills before each commit.
- **Auth:** same-origin session auth. Laravel sets an `XSRF-TOKEN` cookie, and Axios sends it back as `X-XSRF-TOKEN`.
- **Branch:** `REX-0001`, the same PR.

## Rob's decisions

1. **Stack:** React, TypeScript (strict mode), Vite, TanStack Query, Axios, shadcn/ui and React Router. No Laravel starter kit, no Inertia, no Redux or Zustand.
2. **Commits:** the frontend setup is its own commit, made before any SPA features.
3. **Package manager:** pnpm.
4. **Tests:** colocated, `X.test.tsx` next to `X.tsx`.
5. **Entry flow:** a login screen, signed into with the seeded demo user, landing on a list of seeded contacts.
6. **Screens:** as approved in the mockups.
   - `/login`: a centred card with the demo-credentials hint.
   - The app shell has a left sidebar with the logo, Contacts navigation, and the signed-in user with a sign-out icon at the bottom.
   - `/contacts`: a search card with name, phone and email-domain fields with icons, then a table. Each row shows an avatar with initials, the name, "N emails · M phones", email-domain badges and grouped phone numbers, with a row actions menu and pagination.
   - `/contacts/:id`: a header card with a large avatar, Edit and Delete buttons, then Phones and Emails cards. Each phone has a Call button. Below them is a call-outcome card with a colour and icon per outcome.
   - `/contacts/new` and `/contacts/:id/edit`: a side sheet over the list, sharing one form. Emails and phones are lists you can add to and remove from. API errors appear under the named fields.
7. **Icons:** lucide-react (no emoji).
8. **Tooling:** ESLint and Prettier; Vitest with Testing Library; `openapi-typescript` for the API types; `react-hook-form` for forms.
9. **Code layout:** described under "Code layout" below.
10. **Backend support:** a catch-all SPA route, a contacts seeder, and the three items left over from the routing review.
11. **Demo hint:** the demo login hint shows only outside production.

## Design

### Frontend setup commit

**Dependencies:**
- **Runtime:** `react`, `react-dom`, `react-router`, `@tanstack/react-query`, `axios`, `react-hook-form`, `lucide-react`, plus whatever shadcn's chosen components need (Radix packages, `class-variance-authority`, `clsx`, `tailwind-merge`, `sonner` for toasts).
- **Development:**
  - `typescript`, `@types/react`, `@types/react-dom`, `@vitejs/plugin-react`;
  - `eslint` with the TypeScript, React Hooks and React Refresh configs, and `prettier`;
  - `vitest`, `@testing-library/react`, `@testing-library/user-event`, `@testing-library/jest-dom`, `jsdom`;
  - `openapi-typescript`.

**Configuration:**
- `tsconfig.json` with strict mode and the path alias `@/*` pointing to `resources/js/*`.
- `vite.config.ts`, renamed from `.js`, adding the React plugin and the alias. Vitest configuration, either in it or alongside it, uses the `jsdom` environment and a setup file that registers `jest-dom`.
- `eslint.config.js` (flat config) and `.prettierrc`. Generated output is ignored: `resources/js/components/ui/**` and the generated API types file.

**`package.json` scripts:**
- `dev` and `build` (which runs the type-check plus `vite build`)
- `typecheck` (`tsc --noEmit`)
- `lint`
- `format`
- `test` (`vitest run`)
- `api:types` (`openapi-typescript app/Modules/Contacts/Presentation/Http/openapi.json -o resources/js/lib/api/schema.d.ts`)

The generated `schema.d.ts` is committed.

**shadcn:** initialise it for Tailwind 4 with the zinc base colour and components in `resources/js/components/ui`. Add only the components used: button, input, label, card, table, badge, avatar, dropdown-menu, sheet, alert-dialog, alert, form or field, separator, skeleton, and sonner. It runs through `pnpm dlx`, which asks Rob for approval.

**Entry point:** `resources/js/app.tsx` replaces `app.js`. The Blade shell lives at `resources/views/app.blade.php`.

**ESLint packages:** `@eslint/js`, `typescript-eslint`, `eslint-plugin-react-hooks`, `eslint-plugin-react-refresh` and `globals`. Each one goes through the dependency approval prompt.

**Contents of the setup commit:**
- the tooling, configs and scripts above;
- `vite.config.ts` with the entry `resources/js/app.tsx`, keeping the existing Instrument Sans Bunny font;
- a minimal `app.tsx` that mounts a placeholder into `#app`;
- the `app.blade.php` shell;
- shadcn initialisation.

The SPA catch-all route comes in the backend-support commit that follows, so `app.tsx` already exists when it lands. The hook check is to edit `app.tsx` and confirm the `lint-frontend` hook runs cleanly. There is no throwaway file.

**If shadcn is blocked:** if `pnpm dlx shadcn` or a component's dependency fails `minimumReleaseAge` or `strictDepBuilds`, pin an older version that passes. Never add exclusions, and never write the components by hand.

**Vitest setup file:** registers `jest-dom`, and stubs `crypto.randomUUID` if jsdom doesn't provide it.

### Code layout

```
resources/js/
├── app.tsx                      router + QueryClient providers
├── router.tsx                   route tree
├── components/ui/               shadcn, generated, not edited
├── components/                  app-wide components (AppShell, NotFound, ErrorBoundary)
├── lib/
│   ├── api/client.ts            Axios instance (credentials, XSRF, Accept JSON)
│   ├── api/problem.ts           RFC 9457 Problem type + helpers (isProblem, fieldErrors)
│   ├── api/schema.d.ts          generated from openapi.json, not edited
│   └── utils.ts                 shadcn cn()
└── modules/
    ├── auth/                    api (login, logout, me), useCurrentUser, LoginPage, requireAuth loader
    └── contacts/
        ├── api/                 typed calls + query keys + hooks (useContacts, useContact, useSaveContact, useDeleteContact, useCallContact)
        ├── components/          ContactsTable, ContactSearch, ContactForm, ContactAvatar, CallOutcome, PhoneNumber
        └── routes/              ContactsListPage, ContactDetailPage, ContactSheet (new/edit)
```

Tests sit next to each file as `*.test.ts` or `*.test.tsx`. The module boundaries mirror the backend's `app/Modules/Contacts`.

### Routing (React Router, data mode)

- **Router factory:** the router is built by `createAppRouter(queryClient)`, so loaders can use the QueryClient and tests can pass their own.
- **Setup:** `createBrowserRouter` with a root layout. Public route: `/login`. Every other route sits under the authenticated `AppShell` layout, whose loader runs `requireAuth`. It fetches `/api/v1/me` through the QueryClient (`ensureQueryData`) and redirects to `/login?redirect=<path>` on a 401.
- **Routes:**
  - `/` redirects to `/contacts`.
  - `/contacts` is the list.
  - `/contacts/new` and `/contacts/:id/edit` are child routes of the list, rendering the side sheet over it.
  - `/contacts/:id` is the detail page.
  - `*` is a not-found page.
- **Search and paging in the URL:** they live in `useSearchParams`, as `?name=&phone=&email_domain=&page=`. Typing in a search field updates the URL after a short debounce of about 300 ms and resets to page 1.
- **After login:** go to the `redirect` parameter if it is a same-origin path starting with `/`, otherwise `/contacts`. This prevents an open redirect.

### Data and API

- **Axios client:**
  - `withCredentials: true` and `withXSRFToken: true`;
  - headers `Accept: application/json` and `X-Requested-With: XMLHttpRequest`;
  - base URL `/`.

  A response interceptor turns error responses with `application/problem+json` into a typed `ApiProblem` error. It carries `status`, `code`, `title`, `detail` and an optional `errors` map.
- **Typed calls:** every call is typed from `schema.d.ts` (`paths[...]` and `components['schemas'][...]`). There are no hand-written response types.
- **TanStack Query:**
  - query keys come from a key factory, `contactKeys.all`, `.list(params)` and `.detail(id)`;
  - mutations invalidate the list and detail keys;
  - `staleTime` is about 30 s, and nothing is retried on 4xx.
- **The CSRF cookie:** Laravel issues `XSRF-TOKEN` on any web response, including the SPA shell and every API response, because the API routes use the `web` middleware group. The cookie therefore exists before the first POST.
- **On a 419:** the interceptor retries the original request once, since the 419 response itself carries a fresh token cookie. If the retry fails too, it shows the problem. No page reload is involved, so form state is kept.
- **Response shapes:** these come from `openapi.json` and its generated types. Don't guess them.
  - Single contact and call responses are wrapped in `data`.
  - The list is `{data, links, meta}`, using Laravel's pagination `meta`.
  - Phones are E.164 strings.
  - The UI doesn't offer a choice of `per_page`; it uses the default of 25.
- **Edit page:** it loads the contact with `GET` before showing the form. A missing or deleted contact therefore shows "Contact not found" there. (`PUT` would otherwise create it, because PUT is the idempotent upsert.)
- **Idempotency-Key** is sent only on the two POSTs, create and call. PUT and DELETE are idempotent by design.
- **Idempotency keys:**
  - *Create:* the create form generates one `Idempotency-Key` (`crypto.randomUUID()`) when it opens, and reuses it for every submit until it succeeds. A double-click or a retry after a network error therefore creates one contact.
  - *Calls:* each Call click generates a fresh key. If the request fails with a network error, that same key is reused for the automatic retry.
- **Saving:**
  - *Create* sends `POST /contacts` and then navigates to `/contacts/{id}`, taken from the response.
  - *Edit* sends `PUT /contacts/{id}`.
- **Delete:** `DELETE /contacts/{id}`, behind an alert dialog, then navigates to `/contacts` with a "Contact deleted" toast.
- **Call:** `POST /contacts/{id}/calls` with `{ phone }`. The API has no call history (a backend non-goal), so the outcome card shows the latest result from this session only, held in the call mutation's state. Before any call it shows an empty state: "No calls yet. Use Call next to a number." The card shows the outcome badge, the number dialled and a relative time. A 502 (`telephony_failed`) shows a red alert with the API's `detail`. A 409 (`idempotency_request_in_progress`) shows a neutral "Call already in progress" message.

### Errors in the UI

- **Validation (422 `validation_failed`):** each `errors[key]` is mapped onto the form field with that name (`name`, `emails.1`, `phones.0`) using `react-hook-form`'s `setError`. A summary alert at the top of the form covers any error key that doesn't match a field.
- **Login:** 422 `invalid_credentials` shows the API's `detail` under the form. A 429 shows "Too many attempts, try again shortly", plus the `Retry-After` seconds if present.
- **Not found:** a 404 `contact_not_found` on the detail or edit page shows a "Contact not found" state with a link back to the list.
- **Signed out:** a 401 anywhere after login (the session expired) clears the cached user and redirects to `/login?redirect=<current>`.
- **Everything else:** toasts and alerts always use the problem's `title` and `detail`, never raw messages from Axios. A route error boundary catches render errors.

### Display details

- **Phones:** E.164 is shown grouped for readability by a small formatter, for example `+61 412 345 678`, `+61 2 9876 5432` and `+64 21 234 567`, and stored and sent unchanged.
- **Avatars:** the name's initials, with a background colour chosen deterministically from the contact id out of 4 or 5 soft colours.
- **Email domain badges:** clicking a domain badge in the table applies that `email_domain` filter. This is a small touch that shows off search.
- **Loading:** skeleton rows in the table and skeleton cards on the detail page. **Empty state:** "No contacts match your search", with a Clear button.

### Backend support (one commit, before the SPA)

- **SPA catch-all:** `routes/web.php` replaces the welcome route with a catch-all `GET /{any?}` that returns the `app` Blade view. It's constrained so it never matches `api/*`, `login`, `logout`, `up`, or paths with a file extension. Delete `welcome.blade.php`. The `app.blade.php` shell has `@vite(['resources/css/app.css', 'resources/js/app.tsx'])`, `@viteReactRefresh`, a `<div id="app">`, and a `<meta>` exposing `app.env`, so the SPA can show the demo hint outside production. Test: `GET /contacts/abc` returns the shell with 200, and `GET /api/v1/unknown` still returns a JSON 404 problem.
- **`ContactSeeder`:** about 40 realistic contacts, deterministic (seeded Faker or a fixed list) so every reviewer sees the same data.
  - Mostly AU, plus some NZ.
  - Mixed phone types (AU mobiles `+614…`, AU landlines `+612…` and `+613…`, NZ `+642…`).
  - 1–3 emails each, with several sharing domains such as `rex.com.au` and `example.co.nz`.
  - Some with two phones.

  `DatabaseSeeder` calls it after the demo user. Test: seeding produces about 40 contacts, every phone passes `PhoneNumber`, and every email passes `EmailAddress`.
- **Routing-review leftovers:**
  - guard the idempotency replay against old cache entries, by treating missing `headers` as `[]`;
  - make `ContactDataFromCli` use `ContactId::generate()`;
  - describe `Location` in `openapi.json` as an absolute URL.

### Tests (Vitest and Testing Library, next to the code)

- `lib/api/problem.test.ts`: problem detection, and mapping `errors` to field errors.
- `lib/api/client.test.ts`: the interceptor turns a problem response into an `ApiProblem`.
- `modules/auth/requireAuth.test.ts`: a 401 redirects to `/login?redirect=…`, and an unsafe `redirect` value is ignored.
- `modules/contacts/components/ContactForm.test.tsx`: API 422 errors appear under the right fields, and add/remove email and phone works.
- `modules/contacts/routes/ContactsListPage.test.tsx`: renders rows from a mocked API, and the search input updates the URL query.
- `modules/contacts/components/CallOutcome.test.tsx`: each outcome renders its label, and `telephony_failed` renders the alert.
- `modules/contacts/components/PhoneNumber.test.tsx`: grouping for AU mobile, AU landline and NZ numbers.

API calls are mocked at the Axios or module boundary with `vi.mock`, which needs no extra dependency. Add MSW only if a test genuinely needs it, and that would need Rob's approval.

## Calls made without Rob's explicit input

All of these were shown to Rob in chat and approved as a set, but they began as my guesses:
- ESLint, Prettier, Vitest with Testing Library, `openapi-typescript` and `react-hook-form`.
- The module layout under `resources/js/modules`.
- The demo-credentials hint only outside production, exposed through a meta tag.
- The form as a side sheet with its own URL.
- About 40 deterministic AU and NZ seeded contacts.
- Search terms and paging in the URL with a debounce; clicking a domain badge filters by it.
- Idempotency-key handling: one key per create-form session, a fresh key per call.
- One shell reload on a 419; a 401 after login sends the user back to `/login` with a redirect.
- Phones shown grouped for readability.
- `sonner` for toasts.
- Commit order: frontend setup (including the Blade shell and a minimal `app.tsx`), then backend support (catch-all route, seeder, leftovers), then the SPA. The Instrument Sans font is kept.
- The router is built by a `createAppRouter(queryClient)` factory.
- On a 419, retry the request once rather than reloading the page.
- The call-outcome card shows the latest result in this session, because there's no call history API.
- The demo-credentials hint (`demo@example.com` / `password`, from `DatabaseSeeder`) is written into the SPA and shown only when the meta tag says the environment isn't production.
- If shadcn or its dependencies are too new for `minimumReleaseAge`, pin older versions.
- Building the SPA as a single task, reviewed as one unit (Rob's time budget), unless the plan splits it.

## Left to the builder

- Exact ESLint rule set, beyond the recommended TypeScript, React Hooks and React Refresh configs.
- Component internals, prop names and file splits within the layout above.
- Exact skeleton and empty-state wording.
- The phone grouping rules for less common prefixes (fall back to the raw E.164).
- Whether the Vitest config lives in `vite.config.ts` or `vitest.config.ts`.
