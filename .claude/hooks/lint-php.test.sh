#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=lint-php.sh
export CLAUDE_PROJECT_DIR="$REPO_ROOT"

fixture_dir="$HOOK_TEST_DIR/lint php"
mkdir -p "$fixture_dir"

unformatted='<?php function  foo( ){return 1;}'
file="$fixture_dir/Bad Format.php"
printf '%s' "$unformatted" >"$file"
run_hook "$hook" "$(edit_input Edit "$file" PostToolUse)"
expect_equals 'formats a file whose path has spaces: exit' '0' "$HOOK_EXIT"
if [ "$(cat "$file")" != "$unformatted" ]; then
  test_pass
else
  test_fail 'formats a file whose path has spaces: contents changed'
fi

broken="$fixture_dir/Broken.php"
printf '%s' '<?php function (' >"$broken"
run_hook "$hook" "$(edit_input Edit "$broken" PostToolUse)"
expect_equals 'parse error: exit' '2' "$HOOK_EXIT"
if [ -n "$HOOK_STDERR" ]; then
  test_pass
else
  test_fail 'parse error: stderr should explain the problem'
fi

run_hook "$hook" "$(edit_input Edit /repo/README.md PostToolUse)"
expect_equals 'non-php file: exit' '0' "$HOOK_EXIT"
expect_equals 'non-php file: stdout' '' "$HOOK_STDOUT"
expect_equals 'non-php file: stderr' '' "$HOOK_STDERR"

run_hook "$hook" "$(edit_input Edit "$fixture_dir/Missing.php" PostToolUse)"
expect_equals 'missing php file: exit' '0' "$HOOK_EXIT"

finish_tests
