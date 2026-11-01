<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * An expected failure of a use case, independent of any transport.
 *
 * The message is the human-readable detail. Each entry point maps it to its own output:
 * HTTP through the ProblemRenderer registry, the CLI through its exit codes.
 */
abstract class ApplicationException extends RuntimeException
{
    /**
     * A stable, machine-readable code for the failure, such as `contact_not_found`.
     */
    abstract public function problemCode(): string;
}
