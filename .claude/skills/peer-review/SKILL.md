---
name: peer-review
description: Use when opening a pull request, writing or rewriting a PR description, or summarising a branch for a human reviewer, especially a large branch with many commits or files.
---

# Peer review: PR descriptions

A PR description is the reviewer's map of the change. Reviewers skim, so the description is mostly visual: badges, a diagram, a table and callouts, with short sentences in between. It says what the change is, where to look first, and which decisions to challenge. The code, the specs and the commit log already hold the detail, so the description links to them.

## Gather

1. The base is `main` unless the user names another. The range is `<base>...HEAD`.
2. Run `git log --oneline <base>..HEAD` and `git diff --stat <base>...HEAD`.
3. Find the specs committed on the branch, and the ticket key in the branch name.
4. Run the project's checks on the current HEAD and note each result.
5. Get the repository URL with `gh repo view --json url -q .url`.

## The description

Copy this skeleton and fill every slot. Keep the headings exactly as written.

````markdown
![<Suite>](https://img.shields.io/badge/<Suite>-<count>_passed-brightgreen) ![Lint](https://img.shields.io/badge/lint_%26_types-clean-brightgreen) ![<Backend>](https://img.shields.io/badge/<Backend>-<major>-<brand_colour>) ![<Frontend>](https://img.shields.io/badge/<Frontend>-<major>-<brand_colour>)

## 👋 What this is

<One or two plain sentences: what a user or caller can now do, in the reviewer's terms.>

```mermaid
flowchart LR
    <the entry points and layers the change touches, five to eight nodes>
```

It's a big diff (<N> files), but most of it is <the bulk, such as tests, generated types or scaffolding>. These are the parts worth your time.

## 🧭 Start here

| | What to check | Where |
|---|---|---|
| <emoji> **<Area>** | <The outcome that must hold, in one sentence> | `<File.php>`, `<File.tsx>` |

> [!TIP]
> **Safe to skim:** <generated files, lockfiles, vendored components>.

## 🥊 Push back on these

> [!IMPORTANT]
> - **<The choice>, not <the alternative>.** <What it gains>, but <what it costs>.

## 🚀 Try it

<details>
<summary>Setup (needs <services>)</summary>

```bash
<setup commands>
```

</details>

<The URL and login a reviewer starts from, plus one line on the seeded data if there is any.>

## 📚 Specs

[<Short title>](<repo URL>/blob/<branch>/<path>) · [<Short title>](<repo URL>/blob/<branch>/<path>)
````

The slots have these rules:
- **Title:** it goes in `--title`, not in the body. Write `<branch>: <what it does for a user>`, under 70 characters.
- **Body length:** at most 300 words, leaving out the badges, code blocks, Specs line and attribution. When the slots don't fit, drop rows from the bottom of Start here and Push back until they do, but keep each remaining row a full sentence. Check it with ``sed '/^!\[/d;/^```/,/^```/d;/^## 📚/,$d' <file> | wc -w``.
- **Badges:** at most five, in this order:
  1. one per test suite you ran on this HEAD, with its pass count (`Pest-371_passed`);
  2. one `lint_%26_types-clean` badge covering every linter, type checker and formatter, only when all of them passed;
  3. the backend and frontend framework, at the major version installed (`composer show <package>`, or the `package.json` range).

  A check you didn't run gets no badge. A passing check is `brightgreen` and a failing one `red`. A framework badge uses the framework's brand colour as a hex code (Laravel `FF2D20`, React `61DAFB`). In the badge URL, write a space as `_`, a dash as `--` and `&` as `%26`.
- **Diagram:** show how a request or job flows through the parts the change touches, in five to eight nodes. Label each node in plain words ("Artisan CLI", "Validator"), not class names. A node can stand for a group of classes, and infrastructure the reviewer doesn't need to picture can be left out.
- **Size sentence:** take the file count from `git diff --stat` and name what most of the diff is. Don't work out line counts.
- **Start here:** three to six rows, which also give the reading order. Each row is a behaviour a user or API caller can see, or a design decision that shapes the code. Rank the rows by what it would cost if they were wrong, and put a row that fits two tiers in the higher one:
  1. data integrity and security;
  2. public contracts (API, CLI, schema);
  3. behaviour users see;
  4. internal structure.

  Tooling or setup that the README presents as part of the work, such as `.claude/`, gets a row in the internal structure tier. "What to check" states the outcome as a fact the reviewer can verify, such as "A retry never creates a duplicate contact". "Where" names one or two files by basename, or a folder.
- **Safe to skim:** generated files, lockfiles and vendored components. Leave out anything the Specs line links to.
- **Push back on these:** two to five decisions a reviewer could reasonably disagree with.
- **Try it:** take every command, URL and login from one of these files at HEAD: the README, the `composer.json` or `package.json` scripts, `.env.example` or the seeders. Give a value only when one of them states it. The URL is `APP_URL` from `.env.example`. The summary names the services the reviewer must run (databases, caches, queues). Runtime versions stay in the README.
- **Links:** use absolute URLs to the branch, because GitHub resolves relative links in a PR body against the PR page. Each linked path must exist at HEAD. Check them against `git ls-tree -r HEAD --name-only`.
- **Attribution:** end with the attribution line when the session requires one.
- **Small changes:** for a change under 10 files in `git diff --stat` with one idea, keep only the badges, What this is without the diagram, Start here with one to three rows, and Try it.

## Hand over

1. Write the body to a file outside the repo, in the session scratchpad when there is one. The Bash guards read prose, so the body never goes inline in a command.
2. Give the user the title and the file path, with one line naming the reviewer you wrote for. Unless the user names one, write for an engineer on the team who hasn't seen the branch. VS Code's preview (⇧⌘V) shows everything except the Mermaid diagram.
3. Create or update the PR only when the user says to:

   ```bash
   gh pr create --base <base> --title "<title>" --body-file <file>
   gh pr edit <number> --body-file <file>
   ```
