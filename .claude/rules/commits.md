# Commits

- The subject line is `<branch>: <description>`, for example `REX-0001: add contact validator`. The prefix is the current branch name.
- Never commit on `main` or `master`. Create a branch first.
- Make one commit per completed task. Do not batch unrelated work.
- Pass the message with `-m`, or with the heredoc form `-m "$(cat <<'EOF' ... EOF)"`. Do not use an editor or `-F`.
- Stage explicit paths. Do not use `git add -A` or `git add .`.

The `.claude/hooks/commit-format.sh` hook enforces the subject format and the main-branch ban. Get it right the first time rather than relying on the block.
