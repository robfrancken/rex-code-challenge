<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\SearchTooBroad;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyInvalid;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyMissing;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyReused;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyRequestInProgress;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('renders an unknown contact as a 404 contact_not_found problem', function (): void {
    $id = (string) Str::uuid();

    $this->actingAs(User::factory()->create())
        ->getJson(route('contacts.show', $id))
        ->assertNotFound()
        ->assertExactJson([
            'type' => '/problems/contact_not_found',
            'title' => 'Contact not found',
            'status' => 404,
            'detail' => "No contact exists with id {$id}.",
            'code' => 'contact_not_found',
        ]);
});

it('renders a failed call as a 502 telephony_failed problem', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    $this->actingAs(User::factory()->create())
        ->postJson(route('contacts.calls.store', $id), [], ['Idempotency-Key' => 'k1'])
        ->assertStatus(502)
        ->assertExactJson([
            'type' => '/problems/telephony_failed',
            'title' => 'Call could not be placed',
            'status' => 502,
            'detail' => 'The telephony provider failed to place the call to +61412345678.',
            'code' => 'telephony_failed',
        ]);
});

it('renders a search that runs out of time as a 422 search_too_broad problem', function (): void {
    failTimeLimitedQueries();

    $this->actingAs(User::factory()->create())
        ->getJson(route('contacts.index'))
        ->assertUnprocessable()
        ->assertExactJson([
            'type' => '/problems/search_too_broad',
            'title' => 'Search too broad',
            'status' => 422,
            'detail' => 'The search matched too many contacts to sort in time. Add more characters or another filter.',
            'code' => 'search_too_broad',
        ]);
});

it('does not report the module\'s problems at error level', function (Throwable $exception): void {
    Route::middleware('web')->get('api/test/contacts-problem', fn () => throw $exception);
    Log::spy();

    $this->getJson('api/test/contacts-problem');

    Log::shouldNotHaveReceived('error');
})->with([
    'contact not found' => fn (): Throwable => ContactNotFound::withId('abc'),
    'telephony failed' => fn (): Throwable => TelephonyFailed::forPhone(PhoneNumber::fromString('+61412345678')),
    'search too broad' => fn (): Throwable => SearchTooBroad::make(),
    'idempotency key missing' => fn (): Throwable => IdempotencyKeyMissing::make(),
    'idempotency key invalid' => fn (): Throwable => IdempotencyKeyInvalid::make(),
    'idempotency key reused' => fn (): Throwable => IdempotencyKeyReused::make(),
    'idempotency request in progress' => fn (): Throwable => IdempotencyRequestInProgress::make(),
]);
