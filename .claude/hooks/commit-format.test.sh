#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/testing.sh
source "$here/lib/testing.sh"

hook=commit-format.sh

make_repo() {
  local dir="$HOOK_TEST_DIR/$1"
  if [ ! -d "$dir/.git" ]; then
    mkdir -p "$dir"
    git -C "$dir" init -q
  fi
  printf '%s' "$dir"
}

branch_repo="$(make_repo branch-repo)"
git -C "$branch_repo" symbolic-ref HEAD refs/heads/REX-0001
main_repo="$(make_repo main-repo)"
git -C "$main_repo" symbolic-ref HEAD refs/heads/main
detached_repo="$(make_repo detached-repo)"
if [ -n "$(git -C "$detached_repo" branch --show-current)" ] || ! git -C "$detached_repo" rev-parse -q --verify HEAD >/dev/null; then
  git -C "$detached_repo" -c user.name=t -c user.email=t@t commit -q --allow-empty -m x
  git -C "$detached_repo" checkout -q --detach
fi

heredoc_command() {
  printf 'git commit -m "$(cat <<%s\n%s\n\nbody\nEOF\n)"' "$1" "$2"
}

expect_allowed() {
  local command
  for command in "$@"; do
    expect_decision "allow: $command" "$hook" "$(bash_input "$command" "$branch_repo")" allow
  done
}

expect_denied() {
  local command
  for command in "$@"; do
    expect_decision "deny: $command" "$hook" "$(bash_input "$command" "$branch_repo")" deny
    expect_contains "reason shows format for [$command]" 'REX-0001: <description>' "$HOOK_STDOUT"
  done
}

expect_allowed \
  'git commit -m "REX-0001: add hooks"' \
  "git commit -m 'REX-0001: add hooks'" \
  'git commit -m "REX-0001: add hooks" -m "body"' \
  'git commit --message="REX-0001: add hooks"' \
  'git commit -am "REX-0001: add hooks"' \
  'git add a.txt && git commit -m "REX-0001: add hooks"' \
  "$(heredoc_command "'EOF'" 'REX-0001: add hooks')" \
  "$(heredoc_command 'EOF' 'REX-0001: add hooks')" \
  'git commit --amend --no-edit' \
  'php artisan make:model Contact -m && git commit -m "REX-0001: add model"' \
  'php artisan make:model Contact -m; git add a.txt && git commit -m "REX-0001: add model"' \
  'git -C . commit -m "REX-0001: add hooks"' \
  'git status' \
  'git log --oneline'

expect_denied \
  'git commit -m "add hooks"' \
  'git commit -m "REX-001: add hooks"' \
  'git commit -m "REX-0001:"' \
  'git commit -m "REX-0001:add hooks"' \
  "$(heredoc_command "'EOF'" 'add hooks')" \
  'php artisan make:model Contact -m && git commit -m "add model"' \
  'php artisan make:model Contact -m && git commit' \
  'git commit' \
  'git commit -F msg.txt' \
  'git commit --amend'

for command in 'git commit' 'git commit -F msg.txt' 'git commit --amend'; do
  run_hook "$hook" "$(bash_input "$command" "$branch_repo")"
  expect_contains "reason asks for -m for [$command]" '-m' "$HOOK_STDOUT"
done

expect_decision 'deny: commit on main' "$hook" "$(bash_input 'git commit -m "main: x"' "$main_repo")" deny
expect_contains 'main reason names main' 'main' "$HOOK_STDOUT"
expect_decision 'deny: commit on detached HEAD' "$hook" "$(bash_input 'git commit -m "x: y"' "$detached_repo")" deny
expect_contains 'detached reason explains' 'not on a branch' "$HOOK_STDOUT"

finish_tests
