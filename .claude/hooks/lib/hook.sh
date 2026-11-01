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

# Print the contents of every single- or double-quoted string, one per line.
hook_quoted_strings() {
  CS_TEXT="$1" awk 'BEGIN {
    s = ENVIRON["CS_TEXT"]; n = length(s); q = ""; buf = ""
    for (i = 1; i <= n; i++) {
      c = substr(s, i, 1)
      if (q == "") {
        if (c == "\047" || c == "\042") { q = c; buf = "" }
      } else if (q == "\042" && c == "\\" && i < n) {
        i++; buf = buf substr(s, i, 1)
      } else if (c == q) {
        print buf; q = ""
      } else {
        buf = buf c
      }
    }
  }'
}

# Print one normalised simple command per line (see the task brief for the rules).
# Quote characters are removed (so quoted flags and program names are seen), and
# the contents of each quoted string are also normalised as extra input.
command_segments() {
  local text="$1" line token program rest stripping wrapper skip_next quoted
  local -a tokens
  local backslash_newline=$'\\\n'
  text="${text//"$backslash_newline"/}"
  # "$(" becomes "(" first so the "$" does not survive as a stray segment.
  printf '%s\n' "$text" \
    | sed -e 's/\$(/(/g' \
    | tr -d "\"'" \
    | tr '&;|`(){}' '\n' \
    | while IFS= read -r line; do
        read -r -a tokens <<<"$line" || true
        [ "${#tokens[@]}" -gt 0 ] || continue
        program=""
        rest=""
        stripping=0
        wrapper=""
        skip_next=0
        for token in "${tokens[@]}"; do
          if [ -z "$program" ]; then
            if [ "$skip_next" -eq 1 ]; then
              skip_next=0
              continue
            fi
            case "$token" in
              then | do | else | elif | while | until | if | '!')
                continue
                ;;
              sudo | env | command | exec | nohup | time | nice | xargs)
                stripping=1
                wrapper="$token"
                continue
                ;;
              -*)
                if [ "$stripping" -eq 1 ]; then
                  case "$wrapper:$token" in
                    sudo:-[ughpCU] | nice:-n | env:-[uC] | xargs:-[nILPsdEa])
                      skip_next=1
                      ;;
                  esac
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
  while IFS= read -r quoted; do
    [ -n "$quoted" ] && command_segments "$quoted"
  done < <(hook_quoted_strings "$text")
  return 0
}

# Parse one command_segments line for git. Sets GIT_SUB to the first non-option
# word after "git" (skipping -C/-c style option values) and GIT_ARGS to the
# words after it. Returns 1, with both empty, if the segment's program is not git.
GIT_SUB=""
GIT_ARGS=()
git_parse_segment() {
  local -a words
  local index=1 count word
  read -r -a words <<<"$1" || true
  GIT_SUB=""
  GIT_ARGS=()
  [ "${words[0]:-}" = "git" ] || return 1
  count="${#words[@]}"
  while [ "$index" -lt "$count" ]; do
    word="${words[$index]}"
    case "$word" in
      -C | -c | --git-dir | --work-tree | --namespace)
        index=$((index + 2))
        ;;
      -*)
        index=$((index + 1))
        ;;
      *)
        GIT_SUB="$word"
        if [ $((index + 1)) -lt "$count" ]; then
          GIT_ARGS=("${words[@]:$((index + 1))}")
        fi
        return 0
        ;;
    esac
  done
  return 0
}
