<?php

declare(strict_types=1);

use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Presentation\Queue\CallContactJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('places a call and prints the outcome', function (): void {
    bindGateway(CallOutcome::Voicemail);
    $id = contactWithPhones('+61412345678');

    $this->artisan('contacts:call', ['id' => $id])
        ->expectsOutputToContain('Called +61412345678: voicemail')
        ->assertExitCode(0);
});

it('prints the call as json', function (): void {
    bindGateway(CallOutcome::Voicemail);
    $id = contactWithPhones('+61412345678');

    $this->artisan('contacts:call', [
        'id' => $id,
        '--json' => true,
    ])
        ->expectsOutputToContain('"outcome":"voicemail"')
        ->assertExitCode(0);
});

it('queues the call instead of placing it', function (): void {
    Queue::fake();
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678', '+61498765432');

    $this->artisan('contacts:call', [
        'id' => $id,
        '--phone' => '+61498765432',
        '--queue' => true,
    ])
        ->expectsOutputToContain('Queued call to contact '.$id)
        ->assertExitCode(0);

    Queue::assertPushed(
        CallContactJob::class,
        fn (CallContactJob $job): bool => $job->data->contactId === $id && $job->data->phone?->value === '+61498765432',
    );
    expect($gateway->dialled)->toBe([]);
});

it('does not queue a call to an unknown contact', function (): void {
    Queue::fake();
    $id = (string) Str::uuid();

    $this->artisan('contacts:call', [
        'id' => $id,
        '--queue' => true,
    ])
        ->expectsOutputToContain("No contact exists with id {$id}.")
        ->doesntExpectOutputToContain('Queued')
        ->assertExitCode(1);

    Queue::assertNothingPushed();
});

it('fails when the telephony provider fails', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    $this->artisan('contacts:call', ['id' => $id])->assertExitCode(1);
});

it('rejects a phone the contact does not own', function (): void {
    bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');

    $this->artisan('contacts:call', [
        'id' => $id,
        '--phone' => '+61498765432',
    ])
        ->expectsOutputToContain('phone')
        ->assertExitCode(2);
});
