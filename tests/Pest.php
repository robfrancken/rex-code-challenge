<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('../app', 'Arch');

/**
 * Binds a TelephonyGateway that always returns $outcome and records every number dialled.
 *
 * @return TelephonyGateway&object{dialled: list<string>}
 */
function bindGateway(CallOutcome $outcome): TelephonyGateway
{
    $gateway = new class($outcome) implements TelephonyGateway
    {
        /** @var list<string> */
        public array $dialled = [];

        public function __construct(private readonly CallOutcome $outcome) {}

        public function place(PhoneNumber $to): CallOutcome
        {
            $this->dialled[] = $to->value;

            return $this->outcome;
        }
    };
    app()->instance(TelephonyGateway::class, $gateway);

    return $gateway;
}

/**
 * Creates a contact whose phones are inserted in the given order (so the first has the lowest id) and returns its id.
 */
function contactWithPhones(string ...$phones): string
{
    $contact = ContactFactory::new()->create();
    foreach ($phones as $phone) {
        ContactPhoneFactory::new()->for($contact)->create(['phone' => $phone]);
    }

    return $contact->id;
}

/**
 * A valid upsert request body.
 *
 * @return array{name: string, emails: list<string>, phones: list<string>}
 */
function contactPayload(): array
{
    return [
        'name' => 'Jane Citizen',
        'emails' => ['jane@rex.com.au'],
        'phones' => ['+61412345678'],
    ];
}
