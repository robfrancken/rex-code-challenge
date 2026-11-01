<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Exceptions;

use App\Exceptions\ProblemException;

final class IdempotencyKeyMissing extends ProblemException
{
    public static function make(): self
    {
        return new self('The Idempotency-Key header is required for this endpoint.');
    }

    public function problemCode(): string
    {
        return 'idempotency_key_missing';
    }

    public function status(): int
    {
        return 400;
    }

    public function title(): string
    {
        return 'Idempotency key missing';
    }
}
