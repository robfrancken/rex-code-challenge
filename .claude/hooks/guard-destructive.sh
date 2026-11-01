#!/usr/bin/env bash
# PreToolUse hook for Bash: denies commands that destroy files, history or data.
# Deliberately conservative: it matches inside quoted text too, so
# `echo "rm -rf x"` is denied.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

hint="If this is really needed, ask the user to run it themselves."

# Succeeds when any argument equals one of the given words.
has_arg() {
  local arg word
  for arg in ${GIT_ARGS[@]+"${GIT_ARGS[@]}"}; do
    for word in "$@"; do
      [ "$arg" = "$word" ] && return 0
    done
  done
  return 1
}

check_git() {
  local arg
  case "$GIT_SUB" in
    clean)
      for arg in ${GIT_ARGS[@]+"${GIT_ARGS[@]}"}; do
        case "$arg" in
          --force | -f* | -[!-]*f*) hook_deny "git clean deletes untracked files permanently. Remove a single named file with rm, or ask the user. $hint" ;;
        esac
      done
      ;;
    reset)
      if has_arg --hard; then
        hook_deny "git reset --hard discards uncommitted work. Use git reset --soft or git stash instead, or ask the user. $hint"
      fi
      ;;
    push)
      for arg in ${GIT_ARGS[@]+"${GIT_ARGS[@]}"}; do
        case "$arg" in
          --force* | -f* | -[!-]*f* | +*) hook_deny "Force-pushing rewrites remote history. Push normally, or ask the user. $hint" ;;
        esac
      done
      ;;
    stash)
      if has_arg drop clear; then
        hook_deny "git stash drop/clear permanently loses stashed work. Use git stash pop to keep a record, or ask the user. $hint"
      fi
      ;;
    branch)
      if has_arg -D || { has_arg --delete && has_arg --force; }; then
        hook_deny "Force-deleting a branch loses unmerged commits. Use git branch -d, or ask the user. $hint"
      fi
      ;;
    checkout)
      if has_arg -- .; then
        hook_deny "git checkout -- / . discards uncommitted changes. Use git switch to change branches, or ask the user. $hint"
      fi
      ;;
    restore)
      if ! has_arg --staged || has_arg --worktree -W; then
        hook_deny "git restore discards working tree changes. Use git restore --staged to unstage, or ask the user. $hint"
      fi
      ;;
  esac
}

check_segment() {
  local line="$1" arg previous
  local -a words
  read -r -a words <<<"$line" || true
  [ "${#words[@]}" -gt 0 ] || return 0

  case "${words[0]}" in
    rm)
      for arg in "${words[@]:1}"; do
        case "$arg" in
          --recursive | -r* | -R* | -[!-]*[rR]*) hook_deny "Recursive rm is blocked. Use git rm for tracked files, rm <file> to delete a single named file, rmdir for empty directories, or ask the user. $hint" ;;
        esac
      done
      ;;
    find)
      previous=""
      for arg in "${words[@]:1}"; do
        if [ "$arg" = "-delete" ] || { [ "$arg" = "rm" ] && { [ "$previous" = "-exec" ] || [ "$previous" = "-execdir" ]; }; }; then
          hook_deny "find -delete / -exec rm can remove many files at once. Use git rm, delete a single named file with rm, or ask the user. $hint"
        fi
        previous="$arg"
      done
      ;;
    php | artisan)
      for arg in "${words[@]:1}"; do
        case "$arg" in
          db:wipe | migrate:fresh | migrate:reset) hook_deny "artisan $arg destroys database data. Use php artisan migrate for new migrations, or ask the user. $hint" ;;
        esac
      done
      ;;
    git)
      git_parse_segment "$line" || true
      check_git
      ;;
  esac
}

command="$(hook_field .tool_input.command)"
[ -n "$command" ] || exit 0

while IFS= read -r segment; do
  check_segment "$segment"
done < <(command_segments "$command")

exit 0
