<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\DeleteContact;

final class DeleteContactCommand extends ContactsCommand
{
    protected $signature = 'contacts:delete {id}';

    protected $description = 'Delete a contact';

    public function handle(DeleteContact $action): int
    {
        return $this->guarded(function () use ($action): int {
            $id = (string) $this->argument('id');
            $action->handle($id);
            $this->info("Deleted contact {$id}");

            return self::SUCCESS;
        });
    }
}
