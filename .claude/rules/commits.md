# Commits

- The subject line is `<branch>: <description>`, for example `REX-0001: add contact validator`. The prefix is the current branch name.
- Never commit on `main` or `master`. Create a branch first.
- Make one commit per completed task. Do not batch unrelated work.
- Pass the message with `-m`, or with the heredoc form `-m "$(cat <<'EOF' ... EOF)"`. Do not use an editor or `-F`.
- Stage explicit paths. Do not use `git add -A` or `git add .`.

## Guards also read prose

The Bash guards treat every line and every quoted string in a command as a command. That includes heredoc commit bodies, `gh pr create --body` text, grep patterns and echo strings. A line or backtick span that starts with a guarded command (recursive rm, the npm family, a hard reset, a discarding checkout and so on) is blocked even when it is only text.

- For PR bodies, write the body to a file with the Write tool and use `gh pr create --body-file <file>`.
- In commit messages and bodies, do not start a line or a backtick span with a guarded command. Rephrase instead.
- For a grep or rg pattern containing such text, put the pattern in a file and use `-f`, or rephrase.

The `.claude/hooks/commit-format.sh` hook enforces the subject format and the main-branch ban. Get it right the first time rather than relying on the block.
