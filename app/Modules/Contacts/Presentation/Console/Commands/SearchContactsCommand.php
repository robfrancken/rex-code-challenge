<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\SearchContacts;
use App\Modules\Contacts\Application\Assemblers\SearchContactsDataFromCli;
use App\Modules\Contacts\Presentation\Console\ContactTable;
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;

final class SearchContactsCommand extends ContactsCommand
{
    protected $signature = 'contacts:search {--name=} {--phone=} {--domain=} {--page=1} {--per-page=25} {--json}';

    protected $description = 'Search contacts';

    public function handle(
        SearchContactsDataFromCli $assembler,
        SearchContacts $action,
    ): int {
        return $this->guarded(function () use ($assembler, $action): int {
            $page = $action->handle($assembler->assemble($this->options()));

            if ($this->option('json')) {
                $this->printJson(ContactResource::collection($page->items())->resolve());

                return self::SUCCESS;
            }

            $this->table(ContactTable::headers(), ContactTable::rows($page->items()));
            $this->line("Page {$page->currentPage()} of {$page->lastPage()} ({$page->total()} contacts)");

            return self::SUCCESS;
        });
    }
}
