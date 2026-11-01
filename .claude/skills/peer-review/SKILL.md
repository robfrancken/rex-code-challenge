---
name: peer-review
description: Use when opening a pull request, writing or rewriting a PR description, or summarising a branch for a human reviewer, especially a large branch with many commits or files.
---

# Peer review: PR descriptions

A PR description is the reviewer's map of the change. It says why the change exists, points out the most important aspects and where they live, and names the decisions to challenge. The code, the specs and the commit log already hold the detail, so the description links to them.

## Gather

1. The base is `main` unless the user names another. The range is `<base>...HEAD`.
2. Run `git log --oneline <base>..HEAD` and `git diff --stat <base>...HEAD`.
3. Find the specs, plans and docs committed on the branch, and the ticket key in the branch name.
4. Note the checks you ran on the current HEAD, and their results.

## The description

Copy this skeleton and fill every slot. Keep the bold labels exactly as written.

````markdown
## Why

<One to three sentences: the problem or request, in the reviewer's terms.>

## Most important

1. **<Aspect>.** <What it does, in one sentence.> **Why it matters:** <what goes wrong if this is wrong>. **Where:** `<path>`, `<path>`.
2. ...

**Skim:** <generated files, lockfiles, fixtures, docs>.

## Worth a second opinion

- **<The choice>** over <the alternative not taken>: <the reason>.

## Verify

**Run it:**

```bash
<setup commands>
```

<The URL, login or command a reviewer starts from.>

**Test it:**

- `<command>`: <the result you saw on this HEAD, or "not run">.

## More detail

- [<doc title>](<path>)
````

The slots have these rules:
- **Title:** it goes in `--title`, not in the body. Write `<branch>: <what it does for a user>`, under 70 characters.
- **Body length:** at most 400 words, code blocks included. Check it with `wc -w`.
- **Most important:** three to six entries, which also give the reading order. An aspect is a behaviour a user or API caller can see, or a design decision that shapes the code. Rank by what it would cost if it were wrong:
  1. data integrity and security;
  2. public contracts (API, CLI, schema);
  3. behaviour users see;
  4. internal structure.

  Every entry carries all three labels. If an aspect has no answer for **Why it matters:**, it isn't one of the most important, so leave it out.
- **Run it:** take every command, URL and login from a file at HEAD: the README, the `composer.json` or `package.json` scripts, or the seeders. Give a value only when one of those files states it.
- **Worth a second opinion:** two to five decisions a reviewer could reasonably disagree with.
- **Paths and links:** each one must exist at HEAD. Check them against `git ls-tree -r HEAD --name-only`.
- **Attribution:** end with the attribution line when the session requires one.
- **Small changes:** for a change under 10 files with one idea, keep only Why, Most important (one to three entries) and Verify.

## Hand over

1. Write the body to a file outside the repo, in the session scratchpad when there is one. The Bash guards read prose, so the body never goes inline in a command.
2. Give the user the title and the file path, with one line naming the reviewer you wrote for.
3. Create the PR only when the user says to:

   ```bash
   gh pr create --base <base> --title "<title>" --body-file <file>
   ```
