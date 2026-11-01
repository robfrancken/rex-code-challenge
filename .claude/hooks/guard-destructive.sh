#!/usr/bin/env bash
# PreToolUse hook for Bash: denies commands that destroy files, history or data.
# Deliberately conservative: it matches inside quoted text too, so
# `echo "rm -rf x"` is denied.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

hint="If this is really needed, ask the user to run it themselves. If this was only text (a commit or PR body, a grep pattern), write it to a file or rephrase instead."

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

# Succeeds when the parsed git checkout is a safe branch change: a new branch
# (-b/-B/--orphan name [start]), "-", or one argument that resolves to a commit.
# Everything else (paths, --, ., -f, ...) can discard changes.
checkout_is_safe() {
  local count="${#GIT_ARGS[@]}" repo_dir="$checkout_dir"
  [ "$count" -gt 0 ] || return 1
  case "${GIT_ARGS[0]}" in
    -b | -B | --orphan)
      [ "$count" -ge 2 ] && [ "$count" -le 3 ] || return 1
      case "${GIT_ARGS[1]}" in -*) return 1 ;; esac
      if [ "$count" -eq 3 ]; then
        case "${GIT_ARGS[2]}" in -*) return 1 ;; esac
      fi
      return 0
      ;;
  esac
  [ "$count" -eq 1 ] || return 1
  [ "${GIT_ARGS[0]}" = "-" ] && return 0
  case "${GIT_ARGS[0]}" in -*) return 1 ;; esac
  if [ -n "$GIT_C_DIR" ]; then
    case "$GIT_C_DIR" in
      /*) repo_dir="$GIT_C_DIR" ;;
      *) repo_dir="$checkout_dir/$GIT_C_DIR" ;;
    esac
  fi
  git -C "$repo_dir" rev-parse --verify -q "${GIT_ARGS[0]}^{commit}" >/dev/null 2>&1
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
          --force* | --delete | -d | -f* | -[!-]*f* | +* | :?*) hook_deny "Force-pushing or deleting remote refs rewrites remote history. Push normally, or ask the user. $hint" ;;
        esac
      done
      ;;
    stash)
      if [ "${GIT_ARGS[0]:-}" = "drop" ] || [ "${GIT_ARGS[0]:-}" = "clear" ]; then
        hook_deny "git stash drop/clear permanently loses stashed work. Use git stash pop to keep a record, or ask the user. $hint"
      fi
      ;;
    branch)
      local deletes=0 forces=0
      for arg in ${GIT_ARGS[@]+"${GIT_ARGS[@]}"}; do
        case "$arg" in
          --delete) deletes=1 ;;
          --force) forces=1 ;;
          --*) ;;
          -D* | -[!-]*D*) deletes=1; forces=1 ;;
          -[!-]*)
            case "$arg" in *d*) deletes=1 ;; esac
            case "$arg" in *f*) forces=1 ;; esac
            ;;
        esac
      done
      if [ "$deletes" -eq 1 ] && [ "$forces" -eq 1 ]; then
        hook_deny "Force-deleting a branch loses unmerged commits. Use git branch -d, or ask the user. $hint"
      fi
      ;;
    checkout)
      if ! checkout_is_safe; then
        hook_deny "git checkout with paths, --, . or -f discards uncommitted changes. Use git switch to change branches, or ask the user. $hint"
      fi
      ;;
    switch)
      for arg in ${GIT_ARGS[@]+"${GIT_ARGS[@]}"}; do
        case "$arg" in
          --force | --discard-changes | -f | -[!-]*f*) hook_deny "git switch --force/--discard-changes throws away uncommitted changes. Use git switch without the flag, or ask the user. $hint" ;;
        esac
      done
      ;;
    restore)
      if { ! has_arg --staged -S; } || has_arg --worktree -W; then
        hook_deny "git restore discards working tree changes. Use git restore --staged to unstage, or ask the user. $hint"
      fi
      ;;
  esac
}

check_segment() {
  local line="$1" arg previous is_artisan
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
        if [ "$arg" = "-delete" ] || { [ "${arg##*/}" = "rm" ] && { [ "$previous" = "-exec" ] || [ "$previous" = "-execdir" ]; }; }; then
          hook_deny "find -delete / -exec rm can remove many files at once. Use git rm, delete a single named file with rm, or ask the user. $hint"
        fi
        previous="$arg"
      done
      ;;
    git)
      git_parse_segment "$line" || true
      check_git
      ;;
    xargs)
      if [ "${words[1]:-}" = "rm" ]; then
        hook_deny "xargs rm deletes whatever it is fed, like find -exec rm. Use git rm, delete a single named file with rm, or ask the user. $hint"
      fi
      ;;
  esac

  is_artisan=0
  for arg in "${words[@]}"; do
    if [ "${arg##*/}" = "artisan" ]; then
      is_artisan=1
    fi
  done
  [ "$is_artisan" -eq 1 ] || return 0
  for arg in "${words[@]:1}"; do
    case "$arg" in
      db:wipe | migrate:fresh | migrate:refresh | migrate:reset) hook_deny "artisan $arg destroys database data. Use php artisan migrate for new migrations, or ask the user. $hint" ;;
    esac
  done
}

checkout_dir="$(hook_field .cwd)"
checkout_dir="${checkout_dir:-${CLAUDE_PROJECT_DIR:-.}}"

command="$(hook_field .tool_input.command)"
[ -n "$command" ] || exit 0

while IFS= read -r segment; do
  check_segment "$segment"
done < <(command_segments "$command")

exit 0
