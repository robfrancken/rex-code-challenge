#!/usr/bin/env bash
# Shared helpers sourced by every Claude Code hook. Targets bash 3.2 (macOS default).
#
# This file deliberately does not call `set -e`/`set -u`/`set -o pipefail`: it is
# sourced, so changing shell options would leak into callers (including tests that
# check exit codes). Hook scripts set `set -euo pipefail` themselves, and every
# function here is written to be safe under it.

if ! command -v jq >/dev/null 2>&1; then
  echo "jq is required by Claude hooks" >&2
  exit 2
fi

HOOK_INPUT=""

# Read stdin into HOOK_INPUT; block (exit 2) if it is not valid JSON.
hook_read_input() {
  HOOK_INPUT="$(cat)"
  if [ -z "$HOOK_INPUT" ] || ! printf '%s' "$HOOK_INPUT" | jq empty >/dev/null 2>&1; then
    echo "Hook input was not valid JSON; blocking to be safe." >&2
    exit 2
  fi
}

# Print a jq filter's result against HOOK_INPUT, or nothing if null/missing.
hook_field() {
  printf '%s' "$HOOK_INPUT" | jq -r "$1 // empty"
}

hook_decide() {
  jq -cn --arg decision "$1" --arg reason "$2" \
    '{hookSpecificOutput: {hookEventName: "PreToolUse", permissionDecision: $decision, permissionDecisionReason: $reason}}'
  exit 0
}

hook_deny() {
  hook_decide deny "$1"
}

hook_ask() {
  hook_decide ask "$1"
}

# Print one normalised simple command per line (see the task brief for the rules).
command_segments() {
  local line token program rest stripping
  local -a tokens
  printf '%s\n' "$1" \
    | sed -e 's/\$(/(/g' \
    | tr '&;|`(){}"'"'" '\n' \
    | while IFS= read -r line; do
        read -r -a tokens <<<"$line" || true
        [ "${#tokens[@]}" -gt 0 ] || continue
        program=""
        rest=""
        stripping=0
        for token in "${tokens[@]}"; do
          if [ -z "$program" ]; then
            case "$token" in
              sudo | env | command | exec | nohup | time | nice | xargs)
                stripping=1
                continue
                ;;
              -*)
                if [ "$stripping" -eq 1 ]; then
                  continue
                fi
                ;;
              [A-Za-z_]*=*)
                continue
                ;;
            esac
            program="${token##*/}"
            program="${program#\\}"
          else
            rest="$rest $token"
          fi
        done
        if [ -n "$program" ]; then
          printf '%s%s\n' "$program" "$rest"
        fi
      done
}
