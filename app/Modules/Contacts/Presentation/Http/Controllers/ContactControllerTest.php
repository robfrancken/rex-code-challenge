<?php

declare(strict_types=1);

use App\Models\User;
use Database\Factories\Contacts\ContactEmailFactory;
use Database\Factories\Contacts\ContactFactory;
use Database\Factories\Contacts\ContactPhoneFactory;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('creates a contact with 201 and updates it with 200', function (): void {
    $id = (string) Str::uuid();

    $created = $this->putJson(route('contacts.update', $id), contactPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'emails', 'phones', 'created_at', 'updated_at']])
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.emails', ['jane@rex.com.au'])
        ->assertJsonPath('data.phones', ['+61412345678']);

    $updated = $this->putJson(route('contacts.update', $id), contactPayload())->assertOk();

    expect(collect($updated->json('data'))->except('updated_at')->all())
        ->toBe(collect($created->json('data'))->except('updated_at')->all());
});

it('rejects an invalid phone', function (): void {
    $this->putJson(route('contacts.update', (string) Str::uuid()), ['phones' => ['0412345678']] + contactPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['phones.0']);
});

it('rejects a non-uuid id on upsert', function (): void {
    $this->putJson(route('contacts.update', 'not-a-uuid'), contactPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['id']);
});

it('shows an existing contact', function (): void {
    $contact = ContactFactory::new()->create();
    ContactEmailFactory::new()->for($contact)->create();
    ContactPhoneFactory::new()->for($contact)->create();

    $this->getJson(route('contacts.show', $contact->id))
        ->assertOk()
        ->assertJsonPath('data.id', $contact->id);
});

it('returns 404 contact_not_found for an unknown uuid', function (): void {
    $this->getJson(route('contacts.show', (string) Str::uuid()))
        ->assertNotFound()
        ->assertJsonPath('code', 'contact_not_found');
});

it('returns 404 contact_not_found for a non-uuid id', function (): void {
    $this->getJson('/api/v1/contacts/abc')
        ->assertNotFound()
        ->assertJsonPath('code', 'contact_not_found')
        ->assertJsonPath('detail', 'No contact exists with id abc.');
    $this->deleteJson('/api/v1/contacts/abc')->assertNotFound()->assertJsonPath('code', 'contact_not_found');
});

it('filters and pages the index by cursor', function (): void {
    foreach (['a@rex.com.au', 'b@rex.com.au', 'c@other.com'] as $email) {
        $contact = ContactFactory::new()->create();
        ContactEmailFactory::new()->for($contact)->create([
            'email' => $email,
            'domain' => Str::after($email, '@'),
        ]);
    }

    $first = $this->getJson(route('contacts.index', [
        'email_domain' => 'rex.com.au',
        'per_page' => 1,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure([
            'data',
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['path', 'per_page', 'next_cursor', 'prev_cursor'],
        ])
        ->assertJsonMissingPath('meta.total');

    $this->getJson(route('contacts.index', [
        'email_domain' => 'rex.com.au',
        'per_page' => 1,
        'cursor' => $first->json('meta.next_cursor'),
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
})->group('fulltext');

it('searches the index with q', function (): void {
    ContactFactory::new()->create(['name' => 'Rex Carter']);
    ContactFactory::new()->create(['name' => 'Bob Brown']);

    $this->getJson(route('contacts.index', ['q' => 'rex']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rex Carter')
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonMissingPath('meta.total');
})->group('fulltext');

it('rejects a malformed cursor with 422 validation_failed', function (): void {
    $this->getJson(route('contacts.index', ['cursor' => 'garbage']))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('cursor', 'errors');
});

it('returns a valid page for a cursor reused with a different q', function (): void {
    foreach (range(1, 3) as $i) {
        ContactFactory::new()->create(['name' => "Smith {$i}"]);
        ContactFactory::new()->create(['name' => "Rex {$i}"]);
    }
    $cursor = $this->getJson(route('contacts.index', [
        'q' => 'smi',
        'per_page' => 1,
    ]))->json('meta.next_cursor');

    $this->getJson(route('contacts.index', [
        'q' => 'rex',
        'cursor' => $cursor,
    ]))
        ->assertOk()
        ->assertJsonStructure([
            'data',
            'meta' => ['next_cursor'],
        ]);
})->group('fulltext');

it('deletes a contact then reports it missing', function (): void {
    $contact = ContactFactory::new()->create();

    $this->deleteJson(route('contacts.destroy', $contact->id))->assertNoContent();
    $this->deleteJson(route('contacts.destroy', $contact->id))
        ->assertNotFound()
        ->assertJsonPath('code', 'contact_not_found');
});

it('returns 405 http_error for a method the contacts collection does not allow', function (): void {
    $this->putJson('/api/v1/contacts', contactPayload())
        ->assertMethodNotAllowed()
        ->assertJsonPath('code', 'http_error');
});

it('rejects guests from the index', function (): void {
    auth()->forgetGuards();

    $this->getJson(route('contacts.index'))
        ->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated');
});

it('creates a contact with a server generated id on store', function (): void {
    $response = $this->postJson(route('contacts.store'), contactPayload(), ['Idempotency-Key' => 'k1'])
        ->assertCreated();

    $id = $response->json('data.id');

    expect(Str::isUuid($id))->toBeTrue()
        ->and($id)->toBe(strtolower($id))
        ->and($response->headers->get('Location'))->toEndWith('/api/v1/contacts/'.$id);

    $this->assertDatabaseCount('contacts', 1);
    $this->assertDatabaseHas('contact_emails', [
        'contact_id' => $id,
        'email' => 'jane@rex.com.au',
    ]);
    $this->assertDatabaseHas('contact_phones', [
        'contact_id' => $id,
        'phone' => '+61412345678',
    ]);
});

it('requires an Idempotency-Key on store', function (): void {
    $this->postJson(route('contacts.store'), contactPayload())
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_missing');

    $this->assertDatabaseCount('contacts', 0);
});

it('replays the same contact when store is retried with the same key and body', function (): void {
    $headers = ['Idempotency-Key' => 'k1'];

    $first = $this->postJson(route('contacts.store'), contactPayload(), $headers)->assertCreated();
    $second = $this->postJson(route('contacts.store'), contactPayload(), $headers)
        ->assertCreated()
        ->assertHeader('Idempotency-Replayed', 'true');

    expect($second->json())->toBe($first->json());
    $this->assertDatabaseCount('contacts', 1);
});

it('rejects store with a reused key and a different body', function (): void {
    $headers = ['Idempotency-Key' => 'k1'];

    $this->postJson(route('contacts.store'), contactPayload(), $headers)->assertCreated();
    $this->postJson(route('contacts.store'), contactPayload(['name' => 'Someone Else']), $headers)
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_reused');
});

it('rejects an invalid store payload without creating anything', function (): void {
    $this->postJson(route('contacts.store'), ['phones' => ['0412345678']] + contactPayload(), ['Idempotency-Key' => 'k1'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['phones.0']);

    $this->assertDatabaseCount('contacts', 0);
});

it('returns 405 http_error for PATCH on a contact', function (): void {
    $this->patchJson('/api/v1/contacts/'.Str::uuid(), contactPayload())
        ->assertMethodNotAllowed()
        ->assertJsonPath('code', 'http_error');
});

it('resolves the restful route names to their uris', function (): void {
    $id = (string) Str::uuid();

    expect(route('contacts.index', absolute: false))->toBe('/api/v1/contacts')
        ->and(route('contacts.store', absolute: false))->toBe('/api/v1/contacts')
        ->and(route('contacts.show', $id, false))->toBe('/api/v1/contacts/'.$id)
        ->and(route('contacts.update', $id, false))->toBe('/api/v1/contacts/'.$id)
        ->and(route('contacts.destroy', $id, false))->toBe('/api/v1/contacts/'.$id)
        ->and(route('contacts.calls.store', $id, false))->toBe('/api/v1/contacts/'.$id.'/calls');
});
