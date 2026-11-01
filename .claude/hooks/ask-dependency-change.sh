#!/usr/bin/env bash
# PreToolUse hook for Bash and Edit|Write|MultiEdit: asks before dependency changes.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

policy="Dependencies are policy-controlled (pnpm minimumReleaseAge and trustPolicy), so confirm with the user."

# Prints the first non-option word after the program of a segment.
first_subcommand() {
  local -a words
  local index=1 count
  read -r -a words <<<"$1" || true
  count="${#words[@]}"
  while [ "$index" -lt "$count" ]; do
    case "${words[$index]}" in
      -C | --dir | -F | --filter | -d | --working-dir) index=$((index + 2)) ;;
      -*) index=$((index + 1)) ;;
      *)
        printf '%s' "${words[$index]}"
        return 0
        ;;
    esac
  done
}

# Succeeds when a non-option word follows the subcommand.
has_package_argument() {
  local -a words
  local index=1 count seen=0
  read -r -a words <<<"$1" || true
  count="${#words[@]}"
  while [ "$index" -lt "$count" ]; do
    case "${words[$index]}" in
      -C | --dir | -F | --filter | -d | --working-dir)
        index=$((index + 1))
        ;;
      -*) ;;
      *)
        if [ "$seen" -eq 1 ]; then
          return 0
        fi
        seen=1
        ;;
    esac
    index=$((index + 1))
  done
  return 1
}

check_segment() {
  local segment="$1" program="${1%% *}" subcommand
  case "$program" in
    pnpm)
      subcommand="$(first_subcommand "$segment")"
      case "$subcommand" in
        add | update | up | upgrade | remove | rm | uninstall | un | dlx | approve-builds)
          hook_ask "Dependency change: [$segment]. $policy"
          ;;
        install | i)
          if has_package_argument "$segment"; then
            hook_ask "Dependency change: [$segment]. $policy"
          fi
          ;;
      esac
      ;;
    composer)
      subcommand="$(first_subcommand "$segment")"
      if [ "$subcommand" = "global" ]; then
        subcommand="$(first_subcommand "${segment/ global / }")"
      fi
      case "$subcommand" in
        require | req | remove | rm | update | upgrade | u)
          hook_ask "Dependency change: [$segment]. $policy"
          ;;
      esac
      ;;
  esac
}

if [ "$(hook_field .tool_name)" = "Bash" ]; then
  command="$(hook_field .tool_input.command)"
  [ -n "$command" ] || exit 0
  while IFS= read -r segment; do
    check_segment "$segment"
  done < <(command_segments "$command")
  exit 0
fi

file_path="$(hook_field .tool_input.file_path)"
case "${file_path##*/}" in
  package.json | composer.json)
    hook_ask "Editing $file_path changes dependencies. $policy"
    ;;
esac

exit 0
