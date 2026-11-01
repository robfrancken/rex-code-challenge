#!/usr/bin/env bash
# PostToolUse hook for Edit|Write|MultiEdit: formats PHP files.
# First lib/split-multiline.php puts multi-parameter declarations and multi-key arrays
# one item per line (no Pint rule can), then Pint fixes indentation and trailing commas.
# Exit 0: nothing to do, or clean/fixed. Exit 2: problems Claude must fix (stderr).
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

file="$(hook_field .tool_input.file_path)"
case "$file" in
  *.php) ;;
  *) exit 0 ;;
esac
[ -f "$file" ] || exit 0

project="${CLAUDE_PROJECT_DIR:-$(hook_field .cwd)}"
[ -x "$project/vendor/bin/pint" ] || exit 0
cd "$project"

splitter="$here/lib/split-multiline.php"
if [ -f "$splitter" ]; then
  status=0
  output="$(php "$splitter" "$file" 2>&1)" || status=$?
  if [ "$status" -ne 0 ]; then
    printf 'Splitting multi-item lists failed for %s:\n%s\n' "$file" "$output" >&2
    exit 2
  fi
fi

status=0
output="$(vendor/bin/pint "$file" 2>&1)" || status=$?
if [ "$status" -ne 0 ]; then
  printf 'Pint failed for %s:\n%s\n' "$file" "$output" >&2
  exit 2
fi

exit 0
