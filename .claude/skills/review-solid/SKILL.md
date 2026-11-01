---
name: review-solid
description: Use when reviewing changed code for SOLID and design problems, before committing, or when asked for a SOLID review. Covers Laravel backend and React + TypeScript frontend.
---

# SOLID review

## Scope

Review `$ARGUMENTS` (files or a diff range). If empty, review `git diff main` (branch commits plus uncommitted tracked changes) and the untracked files from `git ls-files --others --exclude-standard`, reading those files in full. Read the surrounding code before judging a changed line.

## Backend (Laravel / PHP)

- Actions: one job each, typed DTO in, typed result out. Flag Actions that validate, format output or touch the request.
- DTOs: `final readonly` with typed properties. Flag raw arrays crossing into Actions and DTOs with behaviour.
- Controllers and commands: thin. Flag business logic in Presentation.
- Providers: each module registers its own routes and commands. Flag registration scattered elsewhere.
- Interfaces: use one where there are real alternatives or a boundary to a third party. Constructor-inject dependencies rather than calling facades inside domain logic where that blocks testing.
- Open/closed: flag long `match`/`if` chains on a type that grow with each new case.

## Frontend (React / TypeScript)

- Single responsibility: a component or hook that fetches, transforms and renders should be split.
- Open/closed: extend through composition (children, slots, small variants), not boolean flags and growing conditionals.
- Interface segregation: props are narrow. Flag components that take a whole object and use one field.
- Dependency inversion: components use query hooks, not Axios directly.

## Do not flag

- Do not flag: missing interfaces where there is a single implementation and no boundary reason.
- Splitting classes or components that change together for the same reason.
- Small helpers kept inline because extraction would add indirection without benefit.

## Output

One line per finding:

`file:line — severity (high|medium|low) — problem — suggested fix`

If there is nothing to report, output `No findings`. Do not pad the list with style opinions.
