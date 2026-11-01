<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Infrastructure;

use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Random\Randomizer;

final readonly class FakeTelephonyGateway implements TelephonyGateway
{
    public function __construct(private Randomizer $randomizer = new Randomizer) {}

    public function place(PhoneNumber $to): CallOutcome
    {
        $cases = CallOutcome::cases();

        return $cases[$this->randomizer->getInt(0, count($cases) - 1)];
    }
}
