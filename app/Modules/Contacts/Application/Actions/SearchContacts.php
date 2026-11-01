<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Application\Actions;

use App\Modules\Contacts\Application\DTOs\SearchContactsData;
use App\Modules\Contacts\Domain\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class SearchContacts
{
    /**
     * Not a backslash: MySQL string literals treat it as an escape, so it is not portable.
     */
    private const string LIKE_ESCAPE = '!';

    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public function handle(SearchContactsData $data): LengthAwarePaginator
    {
        return Contact::query()
            ->when($data->name !== null, fn (Builder $query) => $query->whereRaw(
                'lower(name) like ? escape ?',
                ['%'.$this->escapeLike(mb_strtolower((string) $data->name)).'%', self::LIKE_ESCAPE],
            ))
            ->when($data->phoneDigits !== null, fn (Builder $query) => $query->whereHas(
                'phones',
                fn (Builder $phones) => $phones->whereRaw(
                    'phone like ? escape ?',
                    ['%'.$this->escapeLike((string) $data->phoneDigits).'%', self::LIKE_ESCAPE],
                ),
            ))
            ->when($data->emailDomain !== null, fn (Builder $query) => $query->whereHas(
                'emails',
                fn (Builder $emails) => $emails->where('domain', $data->emailDomain),
            ))
            ->with(['emails', 'phones'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($data->perPage, page: $data->page);
    }

    private function escapeLike(string $value): string
    {
        return preg_replace('/[!%_]/', self::LIKE_ESCAPE.'$0', $value) ?? $value;
    }
}
