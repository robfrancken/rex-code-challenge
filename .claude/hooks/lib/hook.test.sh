#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=testing.sh
source "$here/testing.sh"
# shellcheck source=hook.sh
source "$here/hook.sh"

expect_segment() {
  local input="$1" want="$2" segments
  segments="$(command_segments "$input")"
  if printf '%s\n' "$segments" | grep -Fxq -- "$want"; then
    test_pass
  else
    test_fail "segments of [$input]" "expected segment: $want" "got: $(printf '%s' "$segments" | tr '\n' '|')"
  fi
}

expect_segment 'a && b' 'a'
expect_segment 'a && b' 'b'
expect_segment 'a; b | c' 'a'
expect_segment 'a; b | c' 'b'
expect_segment 'a; b | c' 'c'
expect_segment $'a\nb' 'a'
expect_segment $'a\nb' 'b'
expect_segment 'cd x && sudo rm -rf y' 'rm -rf y'
expect_segment "bash -c 'rm -rf y'" 'rm -rf y'
expect_segment 'echo "$(rm -rf y)"' 'rm -rf y'
expect_segment 'echo `rm -rf y`' 'rm -rf y'
expect_segment '/bin/rm -rf y' 'rm -rf y'
expect_segment '\rm -rf y' 'rm -rf y'
expect_segment 'env FOO=1 npm install' 'npm install'
expect_segment 'FOO=1 npx tsc' 'npx tsc'
expect_segment 'find . | xargs -0 rm -rf' 'rm -rf'
expect_segment 'a || b &' 'b'
expect_segment '(rm -rf y)' 'rm -rf y'
expect_segment '{ rm -rf y; }' 'rm -rf y'
expect_segment 'for f in a b; do rm -rf $f; done' 'rm -rf $f'
expect_segment 'ls && ! rm -rf x' 'rm -rf x'
expect_segment 'if true; then npm i; fi' 'npm i'

expect_segment 'sudo -u root rm -rf /' 'rm -rf /'
expect_segment 'nice -n 10 rm -rf x' 'rm -rf x'
expect_segment 'xargs -n 1 rm -rf' 'rm -rf'
expect_segment 'env -u VAR rm -rf x' 'rm -rf x'
expect_segment 'FOO=1 sudo rm -rf x' 'rm -rf x'
expect_segment 'rm "-rf" /' 'rm -rf /'
expect_segment '"rm" -rf /' 'rm -rf /'
expect_segment 'rm -rf "/"' 'rm -rf /'
expect_segment $'rm \\\n-rf /' 'rm -rf /'
expect_segment 'echo "a && rm -rf y"' 'rm -rf y'
expect_segment 'git commit -m "REX-0001: switch to pnpm"' 'REX-0001: switch to pnpm'
expect_segment 'git commit -m "REX-0001: switch to pnpm"' 'git commit -m REX-0001: switch to pnpm'

empty_count="$(command_segments ' ;; && ' | grep -c . || true)"
expect_equals 'empty segments are dropped' '0' "$empty_count"

hook_field_value="$(HOOK_INPUT='{"tool_input":{"command":"ls"}}'; hook_field '.tool_input.command')"
expect_equals 'hook_field reads a value' 'ls' "$hook_field_value"
hook_field_missing="$(HOOK_INPUT='{}'; hook_field '.tool_input.command')"
expect_equals 'hook_field is empty when missing' '' "$hook_field_missing"

read_status=0
read_stderr="$(printf 'not json' | (hook_read_input) 2>&1 >/dev/null)" || read_status=$?
expect_equals 'hook_read_input exits 2 on invalid JSON' '2' "$read_status"
expect_equals 'hook_read_input explains the block' 'Hook input was not valid JSON; blocking to be safe.' "$read_stderr"

read_status=0
(printf '' | hook_read_input) 2>/dev/null || read_status=$?
expect_equals 'hook_read_input exits 2 on empty input' '2' "$read_status"

valid_value="$(printf '{"a":"b"}' | { hook_read_input; hook_field '.a'; })"
expect_equals 'hook_read_input accepts valid JSON' 'b' "$valid_value"

deny_out="$(hook_deny 'nope' || true)"
deny_status=0
(hook_deny 'nope' >/dev/null) || deny_status=$?
expect_equals 'hook_deny exits 0' '0' "$deny_status"
expect_equals 'hook_deny decision' 'deny' "$(printf '%s' "$deny_out" | jq -r '.hookSpecificOutput.permissionDecision')"
expect_equals 'hook_deny event name' 'PreToolUse' "$(printf '%s' "$deny_out" | jq -r '.hookSpecificOutput.hookEventName')"
expect_equals 'hook_deny reason' 'nope' "$(printf '%s' "$deny_out" | jq -r '.hookSpecificOutput.permissionDecisionReason')"
ask_out="$(hook_ask 'maybe "quoted"')"
expect_equals 'hook_ask decision' 'ask' "$(printf '%s' "$ask_out" | jq -r '.hookSpecificOutput.permissionDecision')"
expect_equals 'hook_ask reason is JSON-escaped' 'maybe "quoted"' "$(printf '%s' "$ask_out" | jq -r '.hookSpecificOutput.permissionDecisionReason')"

bash_json="$(bash_input 'ls -la' /somewhere)"
expect_equals 'bash_input command' 'ls -la' "$(printf '%s' "$bash_json" | jq -r '.tool_input.command')"
expect_equals 'bash_input cwd' '/somewhere' "$(printf '%s' "$bash_json" | jq -r '.cwd')"
edit_json="$(edit_input Edit /x/y.php PostToolUse)"
expect_equals 'edit_input path' '/x/y.php' "$(printf '%s' "$edit_json" | jq -r '.tool_input.file_path')"
expect_equals 'edit_input event' 'PostToolUse' "$(printf '%s' "$edit_json" | jq -r '.hook_event_name')"

git_parse_segment 'git -C /x -c a=b commit -am msg'
expect_equals 'git_parse_segment skips option values' 'commit' "$GIT_SUB"
expect_equals 'git_parse_segment args' '-am msg' "${GIT_ARGS[*]}"
git_parse_segment 'git status'
expect_equals 'git_parse_segment with no args' '' "${GIT_ARGS[*]-}"
git_parse_segment 'git --no-pager log -1'
expect_equals 'git_parse_segment skips bare options' 'log' "$GIT_SUB"
git_parse_status=0
git_parse_segment 'ls -la' || git_parse_status=$?
expect_equals 'git_parse_segment rejects non-git' '1' "$git_parse_status"
expect_equals 'git_parse_segment clears sub for non-git' '' "$GIT_SUB"

finish_tests
