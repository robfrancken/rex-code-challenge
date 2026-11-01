#!/usr/bin/env bash
set -euo pipefail

claude_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
status=0

while IFS= read -r test_file; do
  printf '== %s\n' "${test_file#"$claude_dir"/}"
  if ! bash "$test_file"; then
    status=1
  fi
done < <(find "$claude_dir" -name '*.test.sh' | sort)

exit "$status"
