<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console;

use App\Modules\Contacts\Domain\Models\Contact;

final class ContactTable
{
    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return ['ID', 'Name', 'Emails', 'Phones'];
    }

    /**
     * @param  iterable<Contact>  $contacts
     * @return list<array{string, string, string, string}>
     */
    public static function rows(iterable $contacts): array
    {
        $rows = [];
        foreach ($contacts as $contact) {
            $rows[] = [
                $contact->id,
                $contact->name,
                $contact->emails->pluck('email')->implode(', '),
                $contact->phones->pluck('phone')->implode(', '),
            ];
        }

        return $rows;
    }
}
