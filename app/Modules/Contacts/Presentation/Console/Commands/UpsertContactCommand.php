<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\UpsertContact;
use App\Modules\Contacts\Application\Assemblers\ContactDataFromCli;
use App\Modules\Contacts\Presentation\Console\ContactTable;
use App\Modules\Contacts\Presentation\Http\Resources\ContactResource;

final class UpsertContactCommand extends ContactsCommand
{
    protected $signature = 'contacts:upsert {id? : Contact UUID; generated when omitted} {--name=} {--email=*} {--phone=*} {--json}';

    protected $description = 'Create or update a contact';

    public function handle(
        ContactDataFromCli $assembler,
        UpsertContact $action,
    ): int {
        return $this->guarded(function () use ($assembler, $action): int {
            /** @var string|null $id */
            $id = $this->argument('id');
            /** @var list<string> $emails */
            $emails = $this->option('email');
            /** @var list<string> $phones */
            $phones = $this->option('phone');

            $result = $action->handle($assembler->assemble($id, (string) $this->option('name'), $emails, $phones));

            if ($this->option('json')) {
                $this->printJson((new ContactResource($result->contact))->resolve());

                return self::SUCCESS;
            }

            $this->info(($result->created ? 'Created' : 'Updated').' contact '.$result->contact->id);
            $this->table(ContactTable::headers(), ContactTable::rows([$result->contact]));

            return self::SUCCESS;
        });
    }
}
