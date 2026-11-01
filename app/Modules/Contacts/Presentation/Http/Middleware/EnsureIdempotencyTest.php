<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Contacts\Domain\Telephony\CallOutcome;
use App\Modules\Contacts\Presentation\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

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
        ->assertJsonPath('code', 'idempotency_key_invalid');
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
        ->and($second->getStatusCode())->toBe($first->getStatusCode())
        ->and($second->headers->get('Content-Type'))->toBe($first->headers->get('Content-Type'))
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

it('returns 409 with Retry-After when a request with the same key is in flight', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $gateway = bindGateway(CallOutcome::Answered);
    $id = contactWithPhones('+61412345678');
    $cacheKey = sprintf('idempotency:%s:%s:%s', $user->getAuthIdentifier(), 'contacts.calls.store', hash('sha256', 'abc'));
    $lock = Cache::lock($cacheKey.':lock', 30);
    expect($lock->get())->toBeTrue();

    $this->postJson(route('contacts.calls.store', $id), [], ['Idempotency-Key' => 'abc'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'idempotency_request_in_progress')
        ->assertHeader('Retry-After', '1');

    expect($gateway->dialled)->toBe([]);
    $lock->release();
});

it('replays the Location header of a stored response', function (): void {
    $headers = ['Idempotency-Key' => 'abc'];

    $first = $this->postJson(route('contacts.store'), contactPayload(), $headers)->assertCreated();
    $second = $this->postJson(route('contacts.store'), contactPayload(), $headers)
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($first->headers->get('Location'))->not->toBeNull()
        ->and($second->headers->get('Location'))->toBe($first->headers->get('Location'));
});

it('never replays a stored Set-Cookie header', function (): void {
    Route::post('api/v1/cookie-probe', fn () => response()->json(['ok' => true], 201)
        ->cookie('probe', 'secret')
        ->header('X-Internal', 'leak'))
        ->middleware(['web', 'auth', EnsureIdempotency::class]);
    $headers = ['Idempotency-Key' => 'abc'];

    $this->postJson('/api/v1/cookie-probe', [], $headers)->assertCreated();
    $replay = $this->postJson('/api/v1/cookie-probe', [], $headers)
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect(collect($replay->headers->getCookies())->map->getName()->all())->not->toContain('probe')
        ->and($replay->headers->has('X-Internal'))->toBeFalse();
});
