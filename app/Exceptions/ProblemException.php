<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

abstract class ProblemException extends RuntimeException
{
    abstract public function problemCode(): string;

    abstract public function status(): int;

    abstract public function title(): string;

    /**
     * @return array<string, mixed>
     */
    public function extensions(): array
    {
        return [];
    }
}
