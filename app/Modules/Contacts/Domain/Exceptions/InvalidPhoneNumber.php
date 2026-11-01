<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Exceptions;

use InvalidArgumentException;

final class InvalidPhoneNumber extends InvalidArgumentException
{
    public static function forValue(): self
    {
        return new self('Phone number must be in E.164 format for Australia (+61) or New Zealand (+64), for example +61412345678.');
    }
}
