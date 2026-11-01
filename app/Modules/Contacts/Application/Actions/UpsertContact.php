<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\UpsertContactData;
use App\Modules\Contacts\Application\DTOs\UpsertResult;
use App\Modules\Contacts\Domain\Models\Contact;
use App\Modules\Contacts\Domain\Models\ContactEmail;
use App\Modules\Contacts\Domain\Models\ContactPhone;
use App\Modules\Contacts\Domain\ValueObjects\EmailAddress;
use App\Modules\Contacts\Domain\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\DB;

final class UpsertContact
{
    /**
     * Retries the whole write when the database reports a deadlock or lost lock.
     */
    private const int TRANSACTION_ATTEMPTS = 3;

    public function handle(UpsertContactData $data): UpsertResult
    {
        return DB::transaction(function () use ($data): UpsertResult {
            [$contact, $created] = $this->lockOrCreate($data);

            $nameChanged = $contact->name !== $data->name;
            if ($nameChanged) {
                $contact->update(['name' => $data->name]);
            }

            $emailsChanged = $this->syncEmails($contact, $data->emails);
            $phonesChanged = $this->syncPhones($contact, $data->phones);

            if (! $created && ! $nameChanged && ($emailsChanged || $phonesChanged)) {
                $contact->touch();
            }

            return new UpsertResult($contact->load(['emails', 'phones']), $created);
        }, attempts: self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Locks the existing row, or creates it and then locks it.
     *
     * Reading first means an update never attempts an INSERT. On MySQL a duplicate-key INSERT takes a shared
     * lock, and two concurrent updates would then deadlock upgrading it to the exclusive row lock.
     * createOrFirst still resolves two concurrent first creates of the same id.
     *
     * @return array{Contact, bool} the locked contact, and whether this call inserted it
     */
    private function lockOrCreate(UpsertContactData $data): array
    {
        $contact = Contact::query()->lockForUpdate()->find($data->id->value);
        if ($contact !== null) {
            return [$contact, false];
        }

        $created = Contact::query()
            ->createOrFirst(['id' => $data->id->value], ['name' => $data->name])
            ->wasRecentlyCreated;

        return [Contact::query()->lockForUpdate()->findOrFail($data->id->value), $created];
    }

    /**
     * Inserts missing emails and deletes ones no longer submitted, leaving unchanged rows alone.
     *
     * @param  list<EmailAddress>  $emails
     * @return bool whether any row was inserted or deleted
     */
    private function syncEmails(
        Contact $contact,
        array $emails,
    ): bool {
        $now = now();
        $rows = [];
        foreach ($emails as $email) {
            $rows[] = [
                'contact_id' => $contact->id,
                'email' => $email->value,
                'domain' => $email->domain(),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = ContactEmail::query()->insertOrIgnore($rows);
        $deleted = ContactEmail::query()
            ->where('contact_id', $contact->id)
            ->whereNotIn('email', array_column($rows, 'email'))
            ->delete();

        return $inserted > 0 || $deleted > 0;
    }

    /**
     * Inserts missing phones and deletes ones no longer submitted, leaving unchanged rows alone.
     *
     * @param  list<PhoneNumber>  $phones
     * @return bool whether any row was inserted or deleted
     */
    private function syncPhones(
        Contact $contact,
        array $phones,
    ): bool {
        $now = now();
        $rows = [];
        foreach ($phones as $phone) {
            $rows[] = [
                'contact_id' => $contact->id,
                'phone' => $phone->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $inserted = ContactPhone::query()->insertOrIgnore($rows);
        $deleted = ContactPhone::query()
            ->where('contact_id', $contact->id)
            ->whereNotIn('phone', array_column($rows, 'phone'))
            ->delete();

        return $inserted > 0 || $deleted > 0;
    }
}
