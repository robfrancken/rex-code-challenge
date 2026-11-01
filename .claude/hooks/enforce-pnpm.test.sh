#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=enforce-pnpm.sh

for command in 'npm install' 'npm run build' 'npx vite' 'yarn add react' 'cd x && npm ci' '/usr/local/bin/npm test' 'FOO=1 npx tsc' 'if true; then npm i; fi' 'for f in a; do npx x; done'; do
  expect_decision "deny: $command" "$hook" "$(bash_input "$command")" deny
  reason="$(printf '%s' "$HOOK_STDOUT" | jq -r '.hookSpecificOutput.permissionDecisionReason // empty' 2>/dev/null || true)"
  expect_contains "reason names pnpm for [$command]" 'pnpm' "$reason"
done

run_hook "$hook" "$(bash_input 'npx vite')"
expect_contains 'npx reason suggests pnpm dlx' 'pnpm dlx' "$HOOK_STDOUT"
expect_contains 'npx reason suggests pnpm exec' 'pnpm exec' "$HOOK_STDOUT"

for command in 'pnpm install' 'pnpm exec vite' 'pnpm dlx shadcn' 'pnpm run build' 'echo done' 'git commit -m "REX-0001: switch to pnpm"'; do
  expect_decision "allow: $command" "$hook" "$(bash_input "$command")" allow
done

finish_tests
