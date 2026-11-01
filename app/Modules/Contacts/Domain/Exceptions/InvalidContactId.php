<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidContactId extends InvalidArgumentException
{
    public static function forValue(): self
    {
        return new self('Contact id must be a UUID.');
    }
}
