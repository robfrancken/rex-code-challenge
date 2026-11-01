<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Exceptions;

use App\Exceptions\ProblemException;

final class IdempotencyRequestInProgress extends ProblemException
{
    public static function make(): self
    {
        return new self('A request with this Idempotency-Key is still being processed. Retry shortly.');
    }

    public function problemCode(): string
    {
        return 'idempotency_request_in_progress';
    }

    public function status(): int
    {
        return 409;
    }

    public function title(): string
    {
        return 'Request in progress';
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return ['Retry-After' => '1'];
    }
}
