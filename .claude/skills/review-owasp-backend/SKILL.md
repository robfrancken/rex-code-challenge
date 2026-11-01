---
name: review-owasp-backend
description: Use when reviewing Laravel backend code for security problems, before committing API, CLI, validation or persistence changes, or when asked for an OWASP backend review.
---

# OWASP backend review

Apply the OWASP API Security Top 10 to the changed Laravel code. For Laravel-specific patterns, read `.claude/skills/laravel-best-practices/rules/security.md` and apply it. Do not restate it here.

## Scope

Review `$ARGUMENTS` (files or a diff range). If empty, review `git diff main` (branch commits plus uncommitted tracked changes) and the untracked files from `git ls-files --others --exclude-standard`, reading those files in full. Read the surrounding code before judging a changed line.

## Checklist

- Object-level authorisation: every endpoint that loads a record by id checks the caller may access that record. Flag lookups that trust the id alone.
- Function-level authorisation: privileged routes sit behind a policy, gate or middleware.
- Mass assignment: no `protected $guarded = []`, no `create($request->all())`. Only validated, explicitly listed fields reach the model.
- Injection: no raw SQL built from input, and no unescaped values in `whereRaw`, `orderBy` or shell commands. Bind parameters.
- Rate limiting: public and write endpoints have throttle middleware.
- Error responses: no stack traces, SQL, paths or internal ids leaked. `APP_DEBUG` is not assumed off.
- Validation of third-party input: data from external APIs, webhooks and files is validated like user input.
- Excessive data exposure: responses return resources with explicit fields, not whole models.
- Authentication and secrets: no credentials in code, logs or responses.

## Do not flag

- Missing authorisation on routes the task explicitly leaves public.
- Hardening unrelated to the changed code.

## Output

One line per finding:

`file:line — severity (high|medium|low) — problem — suggested fix`

If there is nothing to report, output `No findings`. Do not pad the list with style opinions.
