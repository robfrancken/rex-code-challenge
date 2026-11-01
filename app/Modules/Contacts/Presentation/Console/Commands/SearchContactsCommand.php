<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromCli;
use App\Modules\Contacts\Presentation\Console\ContactTable;
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;

final class SearchContactsCommand extends ContactsCommand
{
    protected $signature = 'contacts:search {q? : Text matched against name, phone digits and email domain prefix} {--name=} {--phone=} {--domain=} {--per-page=25} {--cursor=} {--json}';

    protected $description = 'Search contacts';

    public function handle(
        SearchContactsDataFromCli $assembler,
        SearchContacts $action,
    ): int {
        return $this->guarded(function () use ($assembler, $action): int {
            $page = $action->handle($assembler->assemble([
                ...$this->options(),
                'q' => $this->argument('q'),
            ]));

            if ($this->option('json')) {
                $this->printJson([
                    'data' => ContactResource::collection($page->items())->resolve(),
                    'next_cursor' => $page->nextCursor()?->encode(),
                    'prev_cursor' => $page->previousCursor()?->encode(),
                ]);

                return self::SUCCESS;
            }

            $this->table(ContactTable::headers(), ContactTable::rows($page->items()));

            if ($page->hasMorePages()) {
                $this->line('Next page: --cursor='.$page->nextCursor()?->encode());
            }

            return self::SUCCESS;
        });
    }
}
