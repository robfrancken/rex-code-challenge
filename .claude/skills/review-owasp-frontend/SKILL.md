---
name: review-owasp-frontend
description: Use when reviewing React + TypeScript frontend code for security problems, before committing UI, API-client or build-config changes, or when asked for an OWASP frontend review.
---

# OWASP frontend review

## Scope

Review `$ARGUMENTS` (files or a diff range). If empty, review `git diff main...HEAD`. Read the surrounding code before judging a changed line.

## Checklist

- XSS: no `dangerouslySetInnerHTML` with unsanitised data. Flag `href`/`src` built from user input, which allows `javascript:` URLs, and direct DOM writes such as `innerHTML`.
- Token storage: no auth tokens in `localStorage` or `sessionStorage`. Prefer Laravel's session cookie (httpOnly).
- CSRF with Laravel session cookies: the client requests `/sanctum/csrf-cookie` or otherwise sends the `X-XSRF-TOKEN` header, with credentials enabled. Flag state-changing calls that skip it.
- Open redirects: redirect targets taken from query strings or API data are checked against an allowlist or restricted to relative paths.
- Secrets in the bundle: nothing sensitive in `VITE_` variables or source. Everything with that prefix ships to the browser.
- Dependency risk: flag new or changed dependencies that are unmaintained, very new, or pulled from git URLs or tarballs. Any new dependency needs Rob's approval.
- Error handling: API error bodies are not rendered raw to the user.

## Do not flag

- Backend concerns, which belong to `review-owasp-backend`.
- Rendering of plain text through normal JSX, which React escapes.

## Output

One line per finding:

`file:line — severity (high|medium|low) — problem — suggested fix`

If there is nothing to report, output `No findings`. Do not pad the list with style opinions.
