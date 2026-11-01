<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Application\Exceptions\SearchTooBroad;
use App\Modules\Contacts\Application\Search\ContactSearchQuery;
use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Cursor;

final readonly class SearchContacts
{
    /**
     * MySQL's error when a statement runs past its MAX_EXECUTION_TIME.
     */
    private const int EXECUTION_TIME_EXCEEDED = 3024;

    public function __construct(private ContactSearchQuery $search) {}

    /**
     * A broad term can match most contacts, which then all need sorting by name. The optimizer hint
     * bounds that work; it must come straight after SELECT, so it leads the column list.
     *
     * @return CursorPaginator<int, Contact>
     *
     * @throws SearchTooBroad when the search runs past the configured time limit
     */
    public function handle(SearchContactsData $data): CursorPaginator
    {
        $contacts = Contact::query()
            ->selectRaw(sprintf('/*+ MAX_EXECUTION_TIME(%d) */ contacts.*', config()->integer('contacts.search.max_execution_ms')))
            ->with(['emails', 'phones']);

        try {
            return $this->search
                ->apply($contacts, $data->q, $data->name, $data->phone, $data->emailDomain)
                ->orderBy('name')
                ->orderBy('id')
                ->cursorPaginate($data->perPage, ['*'], 'cursor', Cursor::fromEncoded($data->cursor));
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === self::EXECUTION_TIME_EXCEEDED) {
                throw SearchTooBroad::make($exception);
            }

            throw $exception;
        }
    }
}
