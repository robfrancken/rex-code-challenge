<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('requires the Idempotency-Key header', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');

    $this->postJson(route('contacts.calls.store', $id))
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_missing');

    expect($gateway->dialled)->toBe([]);
});

it('rejects an over-long key', function (): void {
    bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');

    $this->postJson(route('contacts.calls.store', $id), [], ['Idempotency-Key' => str_repeat('a', 256)])
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_missing');
});

it('replays a stored response without calling the gateway again', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    $headers = ['Idempotency-Key' => 'abc'];

    $first = $this->postJson(route('contacts.calls.store', $id), [], $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotency-Replayed');
    $second = $this->postJson(route('contacts.calls.store', $id), [], $headers)
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($second->getContent())->toBe($first->getContent())
        ->and($gateway->dialled)->toHaveCount(1);
});

it('rejects a reused key with a different body', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678', '+61498765432');
    $headers = ['Idempotency-Key' => 'abc'];

    $this->postJson(route('contacts.calls.store', $id), ['phone' => '+61412345678'], $headers)->assertOk();
    $this->postJson(route('contacts.calls.store', $id), ['phone' => '+61498765432'], $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect($gateway->dialled)->toHaveCount(1);
});

it('does not cache a 5xx so the same key can be retried', function (): void {
    bindGateway(CallOutcome::Failed);
    $id = contactWithPhones('+61412345678');
    $headers = ['Idempotency-Key' => 'abc'];

    $this->postJson(route('contacts.calls.store', $id), [], $headers)->assertStatus(502);

    $gateway = bindGateway(CallOutcome::Answered);
    $this->postJson(route('contacts.calls.store', $id), [], $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotency-Replayed')
        ->assertJsonPath('data.outcome', 'answered');

    expect($gateway->dialled)->toHaveCount(1);
});

it('scopes keys per user', function (): void {
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    $headers = ['Idempotency-Key' => 'abc'];

    $this->postJson(route('contacts.calls.store', $id), [], $headers)->assertOk();
    $this->actingAs(User::factory()->create());
    $this->postJson(route('contacts.calls.store', $id), [], $headers)
        ->assertOk()
        ->assertHeaderMissing('Idempotency-Replayed');

    expect($gateway->dialled)->toHaveCount(2);
});
