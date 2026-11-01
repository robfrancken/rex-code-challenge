#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=guard-destructive.sh

expect_denied() {
  local command
  for command in "$@"; do
    expect_decision "deny: $command" "$hook" "$(bash_input "$command")" deny
    reason="$(printf '%s' "$HOOK_STDOUT" | jq -r '.hookSpecificOutput.permissionDecisionReason // empty' 2>/dev/null || true)"
    case "$reason" in
      *"git rm"* | *"delete a single named file"* | *"ask the user"*) test_pass ;;
      *) test_fail "reason for [$command]" "expected a recovery hint, got: $reason" ;;
    esac
  done
}

expect_allowed() {
  local command
  for command in "$@"; do
    expect_decision "allow: $command" "$hook" "$(bash_input "$command")" allow
  done
}

expect_denied \
  'rm -rf dir' 'rm -fr dir' 'rm -Rf dir' 'rm -r -f dir' 'rm -r dir' 'rm -R dir' \
  'rm --recursive dir' 'rm -rfv dir' 'cd app && rm -rf vendor' "bash -c 'rm -rf x'" \
  '/bin/rm -rf x' 'echo "rm -rf x"' \
  "find . -name '*.log' -delete" 'find . -type f -exec rm {} \;' \
  'git clean -f' 'git clean -fd' 'git clean -xdf' \
  'git reset --hard' 'git reset --hard HEAD~1' \
  'git push --force' 'git push -f origin main' 'git push origin +main' 'git push --force-with-lease' \
  'git stash drop' 'git stash clear' 'git branch -D feature' 'git branch --delete --force feature' \
  'git checkout -- app/Foo.php' 'git checkout .' 'git restore app/Foo.php' 'git restore .' \
  'php artisan db:wipe' 'php artisan migrate:fresh' 'php artisan migrate:fresh --seed' 'php artisan migrate:reset'

expect_allowed \
  'rm file.txt' 'rm -f file.txt' 'rmdir empty' "find . -name '*.php'" \
  'git rm app/Foo.php' 'git rm -r --cached dir' \
  'git clean -n' 'git reset HEAD app/Foo.php' 'git reset --soft HEAD~1' \
  'git push' 'git push origin REX-0001' \
  'git stash' 'git stash pop' 'git stash list' 'git branch -d merged' \
  'git checkout main' 'git checkout -b new' 'git switch main' 'git restore --staged app/Foo.php' \
  'php artisan migrate' 'php artisan migrate:status' 'pnpm install' 'ls -la'

expect_decision 'allow: no command field' "$hook" "$(edit_input Edit /repo/a.php)" allow

finish_tests
