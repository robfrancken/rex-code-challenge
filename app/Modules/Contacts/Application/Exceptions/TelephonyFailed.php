<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Exceptions;

use App\Exceptions\ProblemException;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

final class TelephonyFailed extends ProblemException
{
    public static function forPhone(PhoneNumber $phone): self
    {
        return new self("The telephony provider failed to place the call to {$phone}.");
    }

    public function problemCode(): string
    {
        return 'telephony_failed';
    }

    public function status(): int
    {
        return 502;
    }

    public function title(): string
    {
        return 'Call could not be placed';
    }
}
