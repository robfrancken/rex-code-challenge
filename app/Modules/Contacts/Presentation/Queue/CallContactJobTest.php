<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Presentation\Queue\CallContactJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

it('places the call and logs it', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    Log::spy();

    (new CallContactJob(CallContactData::fromValidated($id, [])))->handle(app(CallContact::class));

    expect($gateway->dialled)->toBe(['+61412345678']);
    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'contacts.call'
            && array_keys($context) === ['contact_id', 'outcome']
            && $context['contact_id'] === $id
            && $context['outcome'] === 'answered')
        ->once();
});

it('lets a telephony failure propagate so the queue retries', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    (new CallContactJob(CallContactData::fromValidated($id, [])))->handle(app(CallContact::class));
})->throws(TelephonyFailed::class);

it('fails without retrying when the contact does not exist', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $job = (new CallContactJob(CallContactData::fromValidated((string) Str::uuid(), [])))->withFakeQueueInteractions();

    $job->handle(app(CallContact::class));

    $job->assertFailedWith(ContactNotFound::class);
    expect($gateway->dialled)->toBe([]);
});

it('fails without retrying when the phone is no longer one of the contact\'s', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    $job = (new CallContactJob(CallContactData::fromValidated($id, ['phone' => '+61498765432'])))->withFakeQueueInteractions();

    $job->handle(app(CallContact::class));

    $job->assertFailedWith(ValidationException::class);
    expect($gateway->dialled)->toBe([]);
});

it('is queued with retries and backoff', function (): void {
    $job = new CallContactJob(CallContactData::fromValidated((string) Str::uuid(), []));

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30]);
});

it('survives queue serialization with its dto and phone number', function (): void {
    $id = (string) Str::uuid();
    $job = new CallContactJob(CallContactData::fromValidated($id, ['phone' => '+61412345678']));

    $restored = unserialize(serialize($job));

    expect($restored->data->contactId)->toBe($id)
        ->and($restored->data->phone?->value)->toBe('+61412345678');
});
