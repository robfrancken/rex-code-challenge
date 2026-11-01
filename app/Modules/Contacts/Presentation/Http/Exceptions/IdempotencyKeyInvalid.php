<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Exceptions;

use App\Exceptions\ProblemException;

final class IdempotencyKeyInvalid extends ProblemException
{
    public static function make(): self
    {
        return new self('The Idempotency-Key header must be between 1 and 255 characters.');
    }

    public function problemCode(): string
    {
        return 'idempotency_key_invalid';
    }

    public function status(): int
    {
        return 400;
    }

    public function title(): string
    {
        return 'Invalid Idempotency-Key';
    }
}
