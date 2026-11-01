<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Domain\Telephony;

enum CallOutcome: string
{
    case Answered = 'answered';
    case NoAnswer = 'no_answer';
    case Busy = 'busy';
    case Voicemail = 'voicemail';
    case Rejected = 'rejected';
    case Failed = 'failed';
}
