<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @return object{dialled: list<string>}&TelephonyGateway
 */
function bindGateway(CallOutcome $outcome): object
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

function contactWithPhones(string ...$phones): string
{
    $contact = ContactFactory::new()->create();
    foreach ($phones as $phone) {
        ContactPhoneFactory::new()->for($contact)->create(['phone' => $phone]);
    }

    return $contact->id;
}

it('returns the outcome of the call', function (CallOutcome $outcome): void {
    bindGateway($outcome);
    $id = contactWithPhones('+61412345678');

    $result = app(CallContact::class)->handle(new CallContactData($id, null));

    expect($result->outcome)->toBe($outcome)
        ->and($result->contactId)->toBe($id)
        ->and($result->dialled->value)->toBe('+61412345678');
})->with(array_filter(CallOutcome::cases(), fn (CallOutcome $case): bool => $case !== CallOutcome::Failed));

it('throws TelephonyFailed when the provider fails', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    try {
        app(CallContact::class)->handle(new CallContactData($id, null));
    } catch (TelephonyFailed $exception) {
        expect($exception->status())->toBe(502)
            ->and($exception->problemCode())->toBe('telephony_failed')
            ->and($exception->title())->toBe('Call could not be placed')
            ->and($exception->getMessage())->toBe('The telephony provider failed to place the call to +61412345678.');

        return;
    }

    $this->fail('Expected TelephonyFailed.');
});

it('dials the earliest phone when none is given', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678', '+61498765432');

    app(CallContact::class)->handle(new CallContactData($id, null));

    expect($gateway->dialled)->toBe(['+61412345678']);
});

it('dials a given phone that belongs to the contact', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678', '+61498765432');

    app(CallContact::class)->handle(new CallContactData($id, PhoneNumber::fromString('+61498765432')));

    expect($gateway->dialled)->toBe(['+61498765432']);
});

it('rejects a valid phone that is not the contact\'s and never dials', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');

    try {
        app(CallContact::class)->handle(new CallContactData($id, PhoneNumber::fromString('+61498765432')));
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('phone')
            ->and($gateway->dialled)->toBe([]);

        return;
    }

    $this->fail('Expected a ValidationException.');
});

it('throws ContactNotFound for an unknown contact', function (): void {
    bindGateway(CallOutcome::Answered);

    app(CallContact::class)->handle(new CallContactData((string) Str::uuid(), null));
})->throws(ContactNotFound::class);
