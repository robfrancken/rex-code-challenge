<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidEmailAddress extends InvalidArgumentException
{
    public static function forValue(): self
    {
        return new self('Email address must be a valid address such as name@example.com.');
    }
}
