#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=ask-dependency-change.sh

for command in \
  'pnpm add react' 'pnpm add -D vitest' 'cd x && pnpm add y' \
  'pnpm install react' 'pnpm i react' 'pnpm install -D vitest' \
  'pnpm update' 'pnpm up' 'pnpm update react' \
  'pnpm remove react' 'pnpm rm react' 'pnpm uninstall react' 'pnpm un react' \
  'pnpm dlx shadcn@latest init' 'pnpm approve-builds' \
  'composer require laravel/sanctum' 'composer require --dev foo/bar' \
  'composer remove foo/bar' 'composer -d x require y' 'composer --working-dir x update' 'composer global require y' 'composer global update' 'if true; then pnpm add y; fi' 'composer update' 'composer update laravel/framework' \
  'pnpm create vite' 'pnpm create vite my-app' 'pnpx cowsay hi' 'pnpm audit --fix' 'pnpm audit --fix=override' \
  'composer config minimum-stability dev' 'composer config -g github-oauth.github.com token' 'composer bump' 'composer bump --dev-only'; do
  expect_decision "ask: $command" "$hook" "$(bash_input "$command")" ask
  reason="$(printf '%s' "$HOOK_STDOUT" | jq -r '.hookSpecificOutput.permissionDecisionReason // empty' 2>/dev/null || true)"
  expect_contains "reason mentions minimumReleaseAge for [$command]" 'minimumReleaseAge' "$reason"
done

run_hook "$hook" "$(bash_input 'cd x && pnpm add y')"
expect_contains 'reason names the matched segment' 'pnpm add y' "$HOOK_STDOUT"

for path in /repo/package.json /repo/packages/x/package.json; do
  expect_decision "ask: Edit $path" "$hook" "$(edit_input Edit "$path")" ask
  expect_contains "reason names $path" "$path" "$HOOK_STDOUT"
done
expect_decision 'ask: Write composer.json' "$hook" "$(edit_input Write /repo/composer.json)" ask
expect_contains 'reason names composer.json' '/repo/composer.json' "$HOOK_STDOUT"

for command in 'pnpm install' 'pnpm i' 'pnpm install --frozen-lockfile' 'pnpm run build' 'pnpm import' \
  'composer -d x install' 'composer global show' 'composer install' 'composer show' 'composer dump-autoload -o' \
  'pnpm audit' 'pnpm audit --json' 'composer config --list' 'composer audit'; do
  expect_decision "allow: $command" "$hook" "$(bash_input "$command")" allow
done

for path in /repo/app/Foo.php /repo/package-lock.json /repo/package.json.bak; do
  expect_decision "allow: Edit $path" "$hook" "$(edit_input Edit "$path")" allow
done

finish_tests
