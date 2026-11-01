<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Presentation\Console\ContactTable;
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;

final class ShowContactCommand extends ContactsCommand
{
    protected $signature = 'contacts:show {id} {--json}';

    protected $description = 'Show a contact';

    public function handle(GetContact $action): int
    {
        return $this->guarded(function () use ($action): int {
            $contact = $action->handle((string) $this->argument('id'));

            if ($this->option('json')) {
                $this->printJson((new ContactResource($contact))->resolve());
            } else {
                $this->table(ContactTable::headers(), ContactTable::rows([$contact]));
            }

            return self::SUCCESS;
        });
    }
}
