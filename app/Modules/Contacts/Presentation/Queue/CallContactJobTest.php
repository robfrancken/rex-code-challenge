<?php

declare(strict_types=1);

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Presentation\Queue\CallContactJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('places the call and logs it', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    Log::spy();

    (new CallContactJob(CallContactData::fromValidated($id, [])))->handle(app(CallContact::class));

    expect($gateway->dialled)->toBe(['+61412345678']);
    Log::shouldHaveReceived('info')->with('contacts.call', Mockery::type('array'))->once();
});

it('lets a telephony failure propagate so the queue retries', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    (new CallContactJob(CallContactData::fromValidated($id, [])))->handle(app(CallContact::class));
})->throws(TelephonyFailed::class);

it('is queued with retries and backoff', function (): void {
    $job = new CallContactJob(CallContactData::fromValidated((string) Str::uuid(), []));

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30]);
});
