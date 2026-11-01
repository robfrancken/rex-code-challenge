<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Exceptions;

use App\Exceptions\ApplicationException;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

final class TelephonyFailed extends ApplicationException
{
    public static function forPhone(PhoneNumber $phone): self
    {
        return new self("The telephony provider failed to place the call to {$phone}.");
    }

    public function problemCode(): string
    {
        return 'telephony_failed';
    }
}
