#!/usr/bin/env bash
# PostToolUse hook for Edit|Write|MultiEdit: formats and lints ts/tsx/js/jsx files.
# Exit 0: nothing to do, or clean/fixed. Exit 2: lint problems Claude must fix (stderr).
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

file="$(hook_field .tool_input.file_path)"
case "$file" in
  *.ts | *.tsx | *.js | *.jsx) ;;
  *) exit 0 ;;
esac
[ -f "$file" ] || exit 0

project="${CLAUDE_PROJECT_DIR:-$(hook_field .cwd)}"
prettier="$project/node_modules/.bin/prettier"
eslint="$project/node_modules/.bin/eslint"
[ -x "$prettier" ] || [ -x "$eslint" ] || exit 0

cd "$project"

if [ -x "$prettier" ]; then
  "$prettier" --write "$file" >/dev/null 2>&1 || true
fi

if [ -x "$eslint" ]; then
  status=0
  output="$("$eslint" --fix "$file" 2>&1)" || status=$?
  if [ "$status" -ne 0 ]; then
    printf 'ESLint found problems in %s:\n%s\n' "$file" "$output" >&2
    exit 2
  fi
fi

exit 0
