<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    expect(fn () => app(CallContact::class)->handle(new CallContactData($id, null)))
        ->toThrow(fn (TelephonyFailed $exception) => expect($exception->problemCode())->toBe('telephony_failed')
            ->and($exception->getMessage())->toBe('The telephony provider failed to place the call to +61412345678.'));
});

it('logs a warning with only the contact id when the provider fails', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');
    Log::spy();

    expect(fn () => app(CallContact::class)->handle(new CallContactData($id, null)))->toThrow(TelephonyFailed::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => $message === 'contacts.call_failed'
            && $context === ['contact_id' => $id])
        ->once();
});

it('dials the earliest phone when none is given', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61498765432', '+61412345678');

    app(CallContact::class)->handle(new CallContactData($id, null));

    expect($gateway->dialled)->toBe(['+61498765432']);
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

    expect(fn () => app(CallContact::class)->handle(new CallContactData($id, PhoneNumber::fromString('+61498765432'))))
        ->toFailValidationOn('phone');

    expect($gateway->dialled)->toBe([]);
});

it('throws ContactNotFound for an unknown contact', function (): void {
    bindGateway(CallOutcome::Answered);

    app(CallContact::class)->handle(new CallContactData((string) Str::uuid(), null));
})->throws(ContactNotFound::class);

it('rejects a call with no phone given when the contact has no phones', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones();

    expect(fn () => app(CallContact::class)->handle(new CallContactData($id, null)))
        ->toFailValidationOn('phone', 'The contact has no phone numbers.');

    expect($gateway->dialled)->toBe([]);
});

it('treats a non-uuid id as not found without querying', function (): void {
    bindGateway(CallOutcome::Answered);
    DB::enableQueryLog();

    expect(fn () => app(CallContact::class)->handle(new CallContactData('abc', null)))->toThrow(ContactNotFound::class);

    expect(DB::getQueryLog())->toBe([]);
});
