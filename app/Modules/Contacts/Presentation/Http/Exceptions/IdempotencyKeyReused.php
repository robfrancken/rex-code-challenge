<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Exceptions;

use App\Exceptions\ProblemException;

final class IdempotencyKeyReused extends ProblemException
{
    public static function make(): self
    {
        return new self('This Idempotency-Key was already used with a different request.');
    }

    public function problemCode(): string
    {
        return 'idempotency_key_reused';
    }

    public function status(): int
    {
        return 422;
    }

    public function title(): string
    {
        return 'Idempotency key reused';
    }
}
