<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\Search\ContactSearchQuery;
use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Pagination\Cursor;

final readonly class SearchContacts
{
    public function __construct(private ContactSearchQuery $search) {}

    /**
     * @return CursorPaginator<int, Contact>
     */
    public function handle(SearchContactsData $data): CursorPaginator
    {
        $contacts = Contact::query()->with(['emails', 'phones']);

        return $this->search
            ->apply($contacts, $data->q, $data->name, $data->phone, $data->emailDomain)
            ->orderBy('name')
            ->orderBy('id')
            ->cursorPaginate($data->perPage, ['*'], 'cursor', Cursor::fromEncoded($data->cursor));
    }
}
