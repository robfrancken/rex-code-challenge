<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Exceptions;

use App\Exceptions\ApplicationException;
use Throwable;

final class SearchTooBroad extends ApplicationException
{
    public static function make(?Throwable $previous = null): self
    {
        return new self('The search matched too many contacts to sort in time. Add more characters or another filter.', 0, $previous);
    }

    public function problemCode(): string
    {
        return 'search_too_broad';
    }
}
