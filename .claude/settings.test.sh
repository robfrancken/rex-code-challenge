#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=hooks/lib/testing.sh
source "$here/hooks/lib/testing.sh"

settings="$here/settings.json"

if jq empty "$settings" 2>/dev/null; then
  test_pass
else
  test_fail 'settings.json is valid JSON'
fi

while IFS= read -r command; do
  [ -n "$command" ] || continue
  path="${command//\$\{CLAUDE_PROJECT_DIR\}/$REPO_ROOT}"
  if [ -f "$path" ] && [ -x "$path" ]; then
    test_pass
  else
    test_fail "registered hook exists and is executable: $command" "resolved: $path"
  fi
done < <(jq -r '.hooks[]?[]?.hooks[]?.command' "$settings" 2>/dev/null)

registered_count="$(jq '[.hooks[]?[]?.hooks[]?.command] | length' "$settings" 2>/dev/null || echo 0)"
expect_equals 'seven hook handlers registered' 7 "$registered_count"

for rule in 'Bash(rm -r *)' 'Bash(rm -rf *)' 'Bash(npm *)' 'Bash(npx *)' 'Bash(yarn *)'; do
  has="$(jq -r --arg rule "$rule" '(.permissions.deny // []) | index($rule) != null' "$settings" 2>/dev/null || echo false)"
  expect_equals "deny contains $rule" true "$has"
done

for rule in 'Edit(/pnpm-workspace.yaml)' 'Edit(/.npmrc)' 'Edit(/.claude/settings.json)' 'Edit(/.claude/hooks/**)' 'Edit(/.claude/settings.local.json)'; do
  has="$(jq -r --arg rule "$rule" '(.permissions.ask // []) | index($rule) != null' "$settings" 2>/dev/null || echo false)"
  expect_equals "ask contains $rule" true "$has"
done

plugin="$(jq -r '.enabledPlugins["superpowers@claude-plugins-official"] // empty' "$settings" 2>/dev/null || true)"
expect_equals 'superpowers plugin enabled' true "$plugin"

# Echoes the ordered script basenames registered for an event and matcher.
registered_scripts() {
  jq -r --arg event "$1" --arg matcher "$2" \
    '(.hooks[$event] // [])[] | select(.matcher == $matcher) | .hooks[].command | split("/") | last' \
    "$settings" 2>/dev/null | paste -sd, -
}

expect_equals 'PreToolUse Bash hooks' \
  'guard-destructive.sh,enforce-pnpm.sh,commit-format.sh,ask-dependency-change.sh' \
  "$(registered_scripts PreToolUse Bash)"
expect_equals 'PreToolUse edit hooks' 'ask-dependency-change.sh' \
  "$(registered_scripts PreToolUse 'Edit|Write|MultiEdit')"
expect_equals 'PostToolUse edit hooks' 'lint-php.sh,lint-frontend.sh' \
  "$(registered_scripts PostToolUse 'Edit|Write|MultiEdit')"

pre_total="$(jq '[.hooks.PreToolUse[]?.hooks[]?] | length' "$settings" 2>/dev/null || echo 0)"
pre_blocking="$(jq '[.hooks.PreToolUse[]?.hooks[]? | select(.onFailure == "block")] | length' "$settings" 2>/dev/null || echo 0)"
expect_equals 'five PreToolUse handlers registered' 5 "$pre_total"
expect_equals 'every PreToolUse handler fails closed (onFailure block)' 5 "$pre_blocking"
post_blocking="$(jq '[.hooks.PostToolUse[]?.hooks[]? | select(has("onFailure"))] | length' "$settings" 2>/dev/null || echo 1)"
expect_equals 'PostToolUse lint handlers do not set onFailure' 0 "$post_blocking"

finish_tests
