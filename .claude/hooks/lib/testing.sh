#!/usr/bin/env bash
# Test helpers sourced by every *.test.sh. Targets bash 3.2 (macOS default).
# Does not change shell options; test files set their own `set -euo pipefail`.

HOOKS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REPO_ROOT="$(cd "$HOOKS_DIR/../.." && pwd)"
HOOK_TEST_DIR="${TMPDIR:-/tmp}/claude-hook-tests"
mkdir -p "$HOOK_TEST_DIR"

TESTS_PASSED=0
TESTS_FAILED=0
HOOK_STDOUT=""
HOOK_STDERR=""
HOOK_EXIT=0

bash_input() {
  jq -cn --arg command "$1" --arg cwd "${2:-$REPO_ROOT}" \
    '{hook_event_name: "PreToolUse", tool_name: "Bash", tool_input: {command: $command}, cwd: $cwd}'
}

edit_input() {
  jq -cn --arg tool "$1" --arg path "$2" --arg event "${3:-PreToolUse}" --arg cwd "$REPO_ROOT" \
    '{hook_event_name: $event, tool_name: $tool, tool_input: {file_path: $path}, cwd: $cwd}'
}

run_hook() {
  local script="$1" json="$2"
  local err_file="$HOOK_TEST_DIR/stderr.$$"
  HOOK_EXIT=0
  HOOK_STDOUT="$(printf '%s' "$json" | "$HOOKS_DIR/$script" 2>"$err_file")" || HOOK_EXIT=$?
  HOOK_STDERR="$(cat "$err_file")"
}

test_pass() {
  TESTS_PASSED=$((TESTS_PASSED + 1))
}

test_fail() {
  TESTS_FAILED=$((TESTS_FAILED + 1))
  printf 'FAIL: %s\n' "$1" >&2
  shift
  local line
  for line in "$@"; do
    printf '      %s\n' "$line" >&2
  done
}

expect_equals() {
  if [ "$3" = "$2" ]; then
    test_pass
  else
    test_fail "$1" "expected: $2" "actual:   $3"
  fi
}

expect_contains() {
  case "$3" in
    *"$2"*) test_pass ;;
    *) test_fail "$1" "expected to contain: $2" "actual: $3" ;;
  esac
}

expect_decision() {
  local label="$1" script="$2" json="$3" want="$4" got
  run_hook "$script" "$json"
  if [ "$want" = "allow" ]; then
    if [ -z "$HOOK_STDOUT" ] && [ "$HOOK_EXIT" -eq 0 ]; then
      test_pass
    else
      test_fail "$label" "expected allow (empty stdout, exit 0)" "exit: $HOOK_EXIT" "stdout: $HOOK_STDOUT" "stderr: $HOOK_STDERR"
    fi
    return 0
  fi
  got="$(printf '%s' "$HOOK_STDOUT" | jq -r '.hookSpecificOutput.permissionDecision // empty' 2>/dev/null || true)"
  if [ "$HOOK_EXIT" -eq 0 ] && [ "$got" = "$want" ]; then
    test_pass
  else
    test_fail "$label" "expected $want" "exit: $HOOK_EXIT" "stdout: $HOOK_STDOUT" "stderr: $HOOK_STDERR"
  fi
}

finish_tests() {
  printf '%d passed, %d failed\n' "$TESTS_PASSED" "$TESTS_FAILED"
  if [ "$TESTS_FAILED" -gt 0 ]; then
    exit 1
  fi
  exit 0
}
