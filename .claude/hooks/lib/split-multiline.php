<?php

declare(strict_types=1);

/**
 * Splits code that the project style requires on multiple lines, where no Pint rule can:
 *  - function, method and closure declarations with more than one parameter;
 *  - keyed arrays ([... => ...]) with more than one element.
 *
 * It only inserts line breaks after the opening bracket, after each top-level comma and
 * before the closing bracket. Pint then fixes indentation and adds trailing commas.
 *
 * Usage: php split-multiline.php [--check] <file-or-directory>...
 *   default  rewrite files in place and print each file changed
 *   --check  change nothing; print each offending line and exit 1 if any
 */
$arguments = array_slice($argv, 1);
$checkOnly = in_array('--check', $arguments, true);
$paths = array_values(array_filter($arguments, fn (string $argument): bool => $argument !== '--check'));

if ($paths === []) {
    fwrite(STDERR, "Usage: php split-multiline.php [--check] <file-or-directory>...\n");
    exit(2);
}

$offences = 0;

foreach (phpFiles($paths) as $file) {
    $code = (string) file_get_contents($file);
    $tokens = PhpToken::tokenize($code);
    $targets = findTargets($tokens);

    if ($targets === []) {
        continue;
    }

    if ($checkOnly) {
        foreach ($targets as $target) {
            printf("%s:%d: %s must have one item per line\n", $file, $tokens[$target['open']]->line, $target['kind']);
        }
        $offences += count($targets);

        continue;
    }

    file_put_contents($file, split($tokens, $targets));
    printf("split %d in %s\n", count($targets), $file);
}

exit($offences > 0 ? 1 : 0);

/**
 * @param  list<string>  $paths
 * @return iterable<string>
 */
function phpFiles(array $paths): iterable
{
    foreach ($paths as $path) {
        if (is_file($path)) {
            yield $path;

            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php' && ! str_contains($entry->getPathname(), '/vendor/')) {
                yield $entry->getPathname();
            }
        }
    }
}

/**
 * @param  list<PhpToken>  $tokens
 * @return list<array{kind: string, open: int, close: int, commas: list<int>}>
 */
function findTargets(array $tokens): array
{
    $targets = [];

    foreach ($tokens as $index => $token) {
        $open = match (true) {
            $token->is(T_FUNCTION) => parameterListOpen($tokens, $index),
            $token->text === '[' && isArrayLiteral($tokens, $index) => $index,
            $token->is(T_ARRAY) => nextSignificant($tokens, $index),
            default => null,
        };

        if ($open === null || ! in_array($tokens[$open]->text, ['(', '['], true)) {
            continue;
        }

        $list = scanList($tokens, $open);

        if ($list === null || $list['items'] < 2) {
            continue;
        }

        $isArray = ! $token->is(T_FUNCTION);

        if ($isArray && ! $list['keyed']) {
            continue;
        }

        if (isSplit($tokens, $open, $list['close'], $list['commas'])) {
            continue;
        }

        $targets[] = [
            'kind' => $isArray ? 'keyed array' : 'parameter list',
            'open' => $open,
            'close' => $list['close'],
            'commas' => $list['commas'],
        ];
    }

    return $targets;
}

/**
 * @param  list<PhpToken>  $tokens
 */
function parameterListOpen(
    array $tokens,
    int $function,
): ?int {
    for ($i = $function + 1; $i < count($tokens); $i++) {
        if ($tokens[$i]->text === '(') {
            return $i;
        }

        if ($tokens[$i]->text === '{' || $tokens[$i]->text === ';') {
            return null;
        }
    }

    return null;
}

/**
 * A "[" opens an array literal unless it follows something that can be indexed.
 *
 * @param  list<PhpToken>  $tokens
 */
function isArrayLiteral(
    array $tokens,
    int $bracket,
): bool {
    $previous = previousSignificant($tokens, $bracket);

    if ($previous === null) {
        return true;
    }

    $token = $tokens[$previous];

    return ! ($token->is([T_VARIABLE, T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_CONSTANT_ENCAPSED_STRING, T_STATIC])
        || in_array($token->text, [']', ')', '}'], true));
}

/**
 * Walks one bracketed list and reports its top-level commas, item count and whether any item is keyed.
 *
 * @param  list<PhpToken>  $tokens
 * @return array{close: int, commas: list<int>, items: int, keyed: bool}|null
 */
function scanList(
    array $tokens,
    int $open,
): ?array {
    $depth = 0;
    $commas = [];
    $items = 0;
    $itemHasContent = false;
    $itemHasArrowFunction = false;
    $keyed = false;

    for ($i = $open; $i < count($tokens); $i++) {
        $token = $tokens[$i];

        if (in_array($token->text, ['(', '[', '{'], true) || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE])) {
            $depth++;
            if ($depth > 1) {
                $itemHasContent = true;
            }

            continue;
        }

        if (in_array($token->text, [')', ']', '}'], true)) {
            $depth--;

            if ($depth === 0) {
                $items += $itemHasContent ? 1 : 0;

                return [
                    'close' => $i,
                    'commas' => $commas,
                    'items' => $items,
                    'keyed' => $keyed,
                ];
            }

            continue;
        }

        if ($depth !== 1) {
            continue;
        }

        if ($token->text === ',') {
            $items += $itemHasContent ? 1 : 0;
            $commas[] = $i;
            $itemHasContent = false;
            $itemHasArrowFunction = false;

            continue;
        }

        if ($token->isIgnorable()) {
            continue;
        }

        $itemHasContent = true;

        if ($token->is(T_FN)) {
            $itemHasArrowFunction = true;
        }

        if ($token->is(T_DOUBLE_ARROW) && ! $itemHasArrowFunction) {
            $keyed = true;
        }
    }

    return null;
}

/**
 * @param  list<PhpToken>  $tokens
 * @param  list<int>  $commas
 */
function isSplit(
    array $tokens,
    int $open,
    int $close,
    array $commas,
): bool {
    $breaks = [$open, ...$commas];
    $lastSignificant = previousSignificant($tokens, $close);

    if ($lastSignificant !== null && ! in_array($lastSignificant, $commas, true)) {
        $breaks[] = $lastSignificant;
    }

    foreach ($breaks as $position) {
        if (! str_contains(gapAfter($tokens, $position), "\n")) {
            return false;
        }
    }

    return true;
}

/**
 * @param  list<PhpToken>  $tokens
 * @param  list<array{kind: string, open: int, close: int, commas: list<int>}>  $targets
 */
function split(
    array $tokens,
    array $targets,
): string {
    $texts = array_map(fn (PhpToken $token): string => $token->text, $tokens);

    foreach ($targets as $target) {
        foreach ([$target['open'], ...$target['commas']] as $position) {
            breakAfter($tokens, $texts, $position);
        }

        $last = previousSignificant($tokens, $target['close']);

        if ($last !== null && ! in_array($last, $target['commas'], true)) {
            breakAfter($tokens, $texts, $last);
        }
    }

    return implode('', $texts);
}

/**
 * @param  list<PhpToken>  $tokens
 * @param  list<string>  $texts
 */
function breakAfter(
    array $tokens,
    array &$texts,
    int $position,
): void {
    if (str_contains(gapAfter($tokens, $position), "\n")) {
        return;
    }

    $next = $position + 1;

    if (isset($tokens[$next]) && $tokens[$next]->is(T_WHITESPACE)) {
        $texts[$next] = "\n";

        return;
    }

    $texts[$position] .= "\n";
}

/**
 * The whitespace and comments between a token and the next significant token.
 *
 * @param  list<PhpToken>  $tokens
 */
function gapAfter(
    array $tokens,
    int $position,
): string {
    $gap = '';

    for ($i = $position + 1; $i < count($tokens) && $tokens[$i]->isIgnorable(); $i++) {
        $gap .= $tokens[$i]->text;
    }

    return $gap;
}

/**
 * @param  list<PhpToken>  $tokens
 */
function nextSignificant(
    array $tokens,
    int $position,
): ?int {
    for ($i = $position + 1; $i < count($tokens); $i++) {
        if (! $tokens[$i]->isIgnorable()) {
            return $i;
        }
    }

    return null;
}

/**
 * @param  list<PhpToken>  $tokens
 */
function previousSignificant(
    array $tokens,
    int $position,
): ?int {
    for ($i = $position - 1; $i >= 0; $i--) {
        if (! $tokens[$i]->isIgnorable()) {
            return $i;
        }
    }

    return null;
}
