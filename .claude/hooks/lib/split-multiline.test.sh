#!/usr/bin/env bash
set -euo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=testing.sh
source "$here/testing.sh"

script="$here/split-multiline.php"
fixture_dir="$HOOK_TEST_DIR/split-multiline"
mkdir -p "$fixture_dir"

input="$fixture_dir/Input.php"
cat >"$input" <<'PHP'
<?php

namespace App\Fixture;

final class Fixture
{
    public function __construct(private string $a, private int $b) {}

    public function store(string $id, int $count, array $options = ['a' => 1, 'b' => 2]): array
    {
        return ['id' => $id, 'count' => $count, 'nested' => ['x' => 1, 'y' => [1, 2]]];
    }

    public function one(string $id): string
    {
        $closure = function (string $a, string $b): string {
            return $a.$b;
        };

        return $id;
    }
}
PHP

expected="$(cat <<'PHP'
<?php

namespace App\Fixture;

final class Fixture
{
    public function __construct(
        private string $a,
        private int $b,
    ) {}

    public function store(
        string $id,
        int $count,
        array $options = [
            'a' => 1,
            'b' => 2,
        ],
    ): array {
        return [
            'id' => $id,
            'count' => $count,
            'nested' => [
                'x' => 1,
                'y' => [1, 2],
            ],
        ];
    }

    public function one(string $id): string
    {
        $closure = function (
            string $a,
            string $b,
        ): string {
            return $a.$b;
        };

        return $id;
    }
}
PHP
)"

status=0
output="$(php "$script" --check "$input")" || status=$?
expect_equals 'check reports offences: exit' '1' "$status"
expect_contains 'check reports a parameter list' 'parameter list must have one item per line' "$output"
expect_contains 'check reports a keyed array' 'keyed array must have one item per line' "$output"

php "$script" "$input" >/dev/null
(cd "$REPO_ROOT" && vendor/bin/pint "$input" >/dev/null)
expect_equals 'split plus pint gives one item per line' "$expected" "$(cat "$input")"

status=0
php "$script" --check "$input" >/dev/null || status=$?
expect_equals 'check passes after splitting' '0' "$status"

untouched="$fixture_dir/Untouched.php"
cat >"$untouched" <<'PHP'
<?php

function single(string $id): string
{
    $list = [1, 2, 3];
    $index = $options['a'];
    $one = ['only' => fn (int $x, int $y) => $x + $y];
    $call = other($id, $count);
    $short = fn (int $a, int $b) => $a + $b;

    return $id;
}
PHP
before="$(cat "$untouched")"
php "$script" "$untouched" >/dev/null
expect_equals 'leaves lists, index access, single-key arrays, calls and arrow functions alone' "$before" "$(cat "$untouched")"

status=0
php "$script" >/dev/null 2>&1 || status=$?
expect_equals 'no paths: usage error' '2' "$status"

finish_tests
