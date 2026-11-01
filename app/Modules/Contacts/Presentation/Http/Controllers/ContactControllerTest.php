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

    $created = $this->putJson(route('contacts.upsert', $id), contactPayload())
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'emails', 'phones', 'created_at', 'updated_at']])
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.emails', ['jane@rex.com.au'])
        ->assertJsonPath('data.phones', ['+61412345678']);

    $updated = $this->putJson(route('contacts.upsert', $id), contactPayload())->assertOk();

    expect(collect($updated->json('data'))->except('updated_at')->all())
        ->toBe(collect($created->json('data'))->except('updated_at')->all());
});

it('rejects an invalid phone', function (): void {
    $this->putJson(route('contacts.upsert', (string) Str::uuid()), ['phones' => ['0412345678']] + contactPayload())
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['phones.0']);
});

it('rejects a non-uuid id on upsert', function (): void {
    $this->putJson(route('contacts.upsert', 'not-a-uuid'), contactPayload())
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

it('filters and paginates the index', function (): void {
    foreach (['a@rex.com.au', 'b@rex.com.au', 'c@other.com'] as $email) {
        $contact = ContactFactory::new()->create();
        ContactEmailFactory::new()->for($contact)->create([
            'email' => $email,
            'domain' => Str::after($email, '@'),
        ]);
    }

    $this->getJson(route('contacts.index', [
        'email_domain' => 'rex.com.au',
        'per_page' => 1,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonStructure(['links', 'meta']);
});

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
