<?php

declare(strict_types=1);

namespace App\Exceptions;

final class InvalidCredentials extends ProblemException
{
    public static function make(): self
    {
        return new self('These credentials do not match our records.');
    }

    public function problemCode(): string
    {
        return 'invalid_credentials';
    }

    public function status(): int
    {
        return 422;
    }

    public function title(): string
    {
        return 'Invalid credentials';
    }
}
