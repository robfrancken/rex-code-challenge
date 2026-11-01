<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Application\DTOs\UpsertResult;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use Illuminate\Support\Facades\DB;

final class UpsertContact
{
    public function handle(UpsertContactData $data): UpsertResult
    {
        return DB::transaction(function () use ($data): UpsertResult {
            $created = Contact::query()
                ->createOrFirst(['id' => $data->id], ['name' => $data->name])
                ->wasRecentlyCreated;

            $contact = Contact::query()->lockForUpdate()->findOrFail($data->id);

            if ($contact->name !== $data->name) {
                $contact->update(['name' => $data->name]);
            }

            $this->syncEmails($contact, $data);
            $this->syncPhones($contact, $data);

            return new UpsertResult($contact->load(['emails', 'phones']), $created);
        });
    }

    private function syncEmails(
        Contact $contact,
        UpsertContactData $data,
    ): void {
        $now = now();
        $rows = [];
        foreach ($data->emails as $email) {
            $rows[] = [
                'contact_id' => $contact->id,
                'email' => $email->value,
                'domain' => $email->domain(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ContactEmail::query()->insertOrIgnore($rows);
        ContactEmail::query()
            ->where('contact_id', $contact->id)
            ->whereNotIn('email', array_column($rows, 'email'))
            ->delete();
    }

    private function syncPhones(
        Contact $contact,
        UpsertContactData $data,
    ): void {
        $now = now();
        $rows = [];
        foreach ($data->phones as $phone) {
            $rows[] = [
                'contact_id' => $contact->id,
                'phone' => $phone->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        ContactPhone::query()->insertOrIgnore($rows);
        ContactPhone::query()
            ->where('contact_id', $contact->id)
            ->whereNotIn('phone', array_column($rows, 'phone'))
            ->delete();
    }
}
