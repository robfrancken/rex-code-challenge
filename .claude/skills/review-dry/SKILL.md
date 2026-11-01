---
name: review-dry
description: Use when reviewing changed code for duplication, before committing, or when asked for a DRY review. Covers Laravel backend and React + TypeScript frontend.
---

# DRY review

## Scope

Review `$ARGUMENTS` (files or a diff range). If empty, review `git diff main...HEAD`. Read the surrounding code before judging a changed line.

## Backend (Laravel / PHP)

- Validation rules repeated across validators, form requests or commands. One validator per use case, shared by HTTP and CLI.
- Query logic repeated in controllers, actions or commands. Move it to a model scope or a single query method.
- Mapping between DTOs and models written in several places. Keep one mapping point per direction.
- Repeated authorisation, formatting or error-handling blocks that belong in one policy, resource or helper.

## Frontend (React / TypeScript)

- API calls made outside the one client or query-hook layer.
- Types redeclared instead of shared from one definition.
- Repeated JSX patterns that should be one component.
- Copied hook logic (loading, error, form state) that should be one custom hook.

## Do not flag

- Coincidental duplication. Apply the rule of three: merge only when the same logic appears a third time and changes for the same reason.
- Test setup that repeats so each test reads on its own.
- Two pieces of code that look alike but represent different concepts.
- Boilerplate the framework requires, such as provider registration or route definitions.

## Output

One line per finding:

`file:line — severity (high|medium|low) — problem — suggested fix`

If there is nothing to report, output `No findings`. Do not pad the list with style opinions.
