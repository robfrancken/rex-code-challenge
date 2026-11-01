<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\DTOs;

use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;

final readonly class CallResult
{
    public function __construct(
        public string $contactId,
        public PhoneNumber $dialled,
        public CallOutcome $outcome,
        public CarbonImmutable $placedAt,
    ) {}
}
