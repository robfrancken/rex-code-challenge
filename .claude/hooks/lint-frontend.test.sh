#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=lint-frontend.sh

without_tools="$HOOK_TEST_DIR/fe-without-tools"
with_tools="$HOOK_TEST_DIR/fe-with-tools"
mkdir -p "$without_tools" "$with_tools/node_modules/.bin"

printf '#!/usr/bin/env bash\nexit 0\n' >"$with_tools/node_modules/.bin/prettier"
cat >"$with_tools/node_modules/.bin/eslint" <<'STUB'
#!/usr/bin/env bash
file="${!#}"
if grep -q LINT_ERROR "$file"; then
  echo "stub lint error"
  exit 1
fi
exit 0
STUB
chmod +x "$with_tools/node_modules/.bin/prettier" "$with_tools/node_modules/.bin/eslint"

run_in() {
  local project="$1" file="$2"
  CLAUDE_PROJECT_DIR="$project" run_hook "$hook" "$(edit_input Edit "$file" PostToolUse)"
}

printf '<?php echo 1;' >"$with_tools/a.php"
run_in "$with_tools" "$with_tools/a.php"
expect_equals 'php file: exit' '0' "$HOOK_EXIT"
expect_equals 'php file: no output' '' "$HOOK_STDOUT$HOOK_STDERR"

printf 'LINT_ERROR' >"$without_tools/a.tsx"
run_in "$without_tools" "$without_tools/a.tsx"
expect_equals 'no tools installed: exit' '0' "$HOOK_EXIT"
expect_equals 'no tools installed: no output' '' "$HOOK_STDOUT$HOOK_STDERR"

printf 'const a = 1;' >"$with_tools/clean.tsx"
run_in "$with_tools" "$with_tools/clean.tsx"
expect_equals 'clean tsx: exit' '0' "$HOOK_EXIT"

printf 'LINT_ERROR' >"$with_tools/bad.tsx"
run_in "$with_tools" "$with_tools/bad.tsx"
expect_equals 'lint error: exit' '2' "$HOOK_EXIT"
expect_contains 'lint error: stderr' 'stub lint error' "$HOOK_STDERR"

mkdir -p "$with_tools/with space"
printf 'LINT_ERROR' >"$with_tools/with space/bad file.tsx"
run_in "$with_tools" "$with_tools/with space/bad file.tsx"
expect_equals 'lint error with spaces in path: exit' '2' "$HOOK_EXIT"
expect_contains 'lint error with spaces in path: stderr' 'stub lint error' "$HOOK_STDERR"

finish_tests
