<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('places a call and returns the outcome', function (): void {
    $gateway = bindGateway(CallOutcome::Busy);
    $id = contactWithPhones('+61412345678');

    $this->postJson(route('contacts.calls.store', $id), [], ['Idempotency-Key' => 'k1'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['contact_id', 'phone', 'outcome', 'placed_at']])
        ->assertJsonPath('data.contact_id', $id)
        ->assertJsonPath('data.phone', '+61412345678')
        ->assertJsonPath('data.outcome', 'busy');

    expect($gateway->dialled)->toBe(['+61412345678']);
});

it('returns 502 telephony_failed when the call fails', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');

    $this->postJson(route('contacts.calls.store', $id), [], ['Idempotency-Key' => 'k1'])
        ->assertStatus(502)
        ->assertJsonPath('code', 'telephony_failed');
});

it('returns 404 contact_not_found for a non-uuid id', function (): void {
    bindGateway(CallOutcome::Answered);

    $this->postJson(route('contacts.calls.store', 'not-a-uuid'), [], ['Idempotency-Key' => 'k1'])
        ->assertNotFound()
        ->assertJsonPath('code', 'contact_not_found');
});

it('returns 404 contact_not_found for an unknown uuid', function (): void {
    bindGateway(CallOutcome::Answered);

    $this->postJson(route('contacts.calls.store', (string) Str::uuid()), [], ['Idempotency-Key' => 'k1'])
        ->assertNotFound()
        ->assertJsonPath('code', 'contact_not_found');
});

it('rejects a phone that does not belong to the contact', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');

    $this->postJson(route('contacts.calls.store', $id), ['phone' => '+61498765432'], ['Idempotency-Key' => 'k1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['phone']);

    expect($gateway->dialled)->toBe([]);
});
