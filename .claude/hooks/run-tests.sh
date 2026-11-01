#!/usr/bin/env bash
set -euo pipefail

claude_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
status=0
found=0

while IFS= read -r test_file; do
  found=1
  printf '== %s\n' "${test_file#"$claude_dir"/}"
  if ! bash "$test_file"; then
    status=1
  fi
done < <(find "$claude_dir" -name '*.test.sh' | sort)

if [ "$found" -eq 0 ]; then
  echo "No *.test.sh files found under $claude_dir" >&2
  exit 1
fi

exit "$status"
