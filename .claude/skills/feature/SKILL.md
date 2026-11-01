---
name: feature
description: Build a feature or fix by sizing the task and running only as much process as it needs. Invoke as /feature with an optional ticket key and a task description.
argument-hint: "[ticket-key] <task description>"
disable-model-invocation: true
---

# Feature

Task: $ARGUMENTS

Follow these steps in order.

## 1. Branch

Check the current branch. If it is `main` or `master`, create a branch named after the ticket key (for example `REX-0002`). If the arguments have no ticket key, ask for one. Never commit on `main`.

## 2. Size

Classify the task, state the size and the reason in one or two sentences, and let the user override it before continuing.

| Size | Signals | Process |
|---|---|---|
| Trivial | one obvious change, no design decision | `superpowers:test-driven-development` -> review -> commit |
| Medium | a few files, one or two decisions | `superpowers:brainstorming` with the design agreed in chat and no spec document -> `superpowers:test-driven-development` -> review -> commit |
| Large | several components, layers or entry points | `superpowers:brainstorming` with a written spec -> `superpowers:writing-plans` with a dependency tree -> execute -> review -> commit per task |

Do not add ceremony to a trivial task: no spec, no plan.

## 3. Run the process for that size

Follow the process in the table. For a large task, execute the plan with `superpowers:subagent-driven-development` or `superpowers:executing-plans`. Ask the user which they prefer if they have not said.

## 4. Dependency tree (large tasks only)

The plan must contain a "Dependency Tree" section. It has a table with these columns:

| ID | Task | Depends on | Touches | Wave |
|---|---|---|---|---|

- **ID:** a short label such as T1.
- **Depends on:** the IDs this task needs finished first, or none.
- **Touches:** the files or module areas the task changes.
- **Wave:** wave 1 has no dependencies. Wave n depends only on earlier waves.

Tasks in the same wave must not touch overlapping files. Tasks in the same wave can run in parallel.

After the table, add a Mermaid graph (`graph TD`) with one node per task and an edge for each dependency, so it renders in the PR. Describe parallelism neutrally. Do not name any tool that runs the tasks.

## 5. Review before each commit

Run the review skills that match the changed files, passing the paths of every changed and new file as the argument:

- Backend (PHP, `app/`, `database/`, `routes/`):
  - `review-dry`
  - `review-solid`
  - `review-owasp-backend`
- Frontend (`resources/js/`):
  - `review-dry`
  - `review-solid`
  - `review-owasp-frontend`

Fix every finding, or tell the user why one is not being fixed. Then run `superpowers:verification-before-completion` and the narrowest tests that cover the change.

## 6. Commit

Commit as the `commits` rule says: subject `<branch>: <description>`, staged explicit paths, one commit per completed task.
