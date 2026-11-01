#!/usr/bin/env bash
# PreToolUse hook for Bash: commit subjects must be "<branch>: <description>".
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

command="$(hook_field .tool_input.command)"
[ -n "$command" ] || exit 0

is_commit=0
while IFS= read -r segment; do
  if git_parse_segment "$segment" && [ "$GIT_SUB" = "commit" ]; then
    is_commit=1
  fi
done < <(command_segments "$command")
[ "$is_commit" -eq 1 ] || exit 0

cwd="$(hook_field .cwd)"
cwd="${cwd:-${CLAUDE_PROJECT_DIR:-.}}"
branch="$(git -C "$cwd" branch --show-current 2>/dev/null || true)"

if [ -z "$branch" ]; then
  hook_deny "Cannot commit: HEAD is not on a branch (detached HEAD). Check out a branch such as REX-0001 first; commit subjects must look like REX-0001: <description>."
fi
case "$branch" in
  main | master)
    hook_deny "Do not commit directly to $branch. Switch to a ticket branch such as REX-0001; commit subjects must look like REX-0001: <description>."
    ;;
esac

format="${branch}: <description>"

subject=""
found=0
heredoc_regex="<<-?[[:space:]]*['\"]?([A-Za-z_][A-Za-z_0-9]*)['\"]?"
message_regex="(^|[[:space:]])(-[a-zA-Z]*m|--message)[[:space:]=]*(\"([^\"]*)\"|'([^']*)'|([^[:space:]\"']+))"

commit_regex="(^|[^[:alnum:]_-])git[[:space:]]+([^;&|]*[[:space:]])?commit([[:space:]]|\$)"
commit_part="$command"
if [[ "$command" =~ $commit_regex ]]; then
  commit_part="${BASH_REMATCH[0]}${command#*"${BASH_REMATCH[0]}"}"
fi

if [[ "$commit_part" =~ $heredoc_regex ]]; then
  found=1
  subject="$(printf '%s\n' "$commit_part" | awk 'seen && NF { print; exit } /<<-?[[:space:]]*["'"'"']?[A-Za-z_]/ { seen = 1 }')"
elif [[ "$commit_part" =~ $message_regex ]]; then
  found=1
  subject="${BASH_REMATCH[4]}${BASH_REMATCH[5]}${BASH_REMATCH[6]}"
  subject="${subject%%$'\n'*}"
elif [[ "$commit_part" == *--amend* && "$commit_part" == *--no-edit* ]]; then
  exit 0
fi

if [ "$found" -eq 0 ]; then
  hook_deny "Commit message missing. Pass the message with -m so it can be checked; subjects must look like $format."
fi

prefix="${branch}: "
rest="${subject#"$prefix"}"
if [ "$rest" = "$subject" ] || [ -z "$rest" ]; then
  hook_deny "Commit subject must look like $format (for example $prefix"'add hooks'"). Got: ${subject:-<empty>}"
fi
case "$rest" in
  [[:space:]]*) hook_deny "Commit subject must look like $format with a description right after the colon and space. Got: $subject" ;;
esac

exit 0
