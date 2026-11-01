#!/usr/bin/env bash
# PreToolUse hook for Bash: this project uses pnpm only.
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/hook.sh
source "$here/lib/hook.sh"

hook_read_input

command="$(hook_field .tool_input.command)"
[ -n "$command" ] || exit 0

while IFS= read -r segment; do
  program="${segment%% *}"
  case "$program" in
    npm) hook_deny "This project uses pnpm only. Use pnpm instead of npm (for example pnpm install, pnpm run build)." ;;
    npx) hook_deny "This project uses pnpm only. Use pnpm exec for installed binaries or pnpm dlx for one-off packages instead of npx." ;;
    yarn) hook_deny "This project uses pnpm only. Use pnpm instead of yarn." ;;
  esac
done < <(command_segments "$command")

exit 0
