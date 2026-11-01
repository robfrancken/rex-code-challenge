<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Telephony;

use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;

interface TelephonyGateway
{
    public function place(PhoneNumber $to): CallOutcome;
}
