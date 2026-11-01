<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\Actions\GetContact;
use App\Modules\Contacts\Application\Assemblers\CallContactDataFromCli;
use App\Modules\Contacts\Presentation\Http\Resources\CallResource;
use App\Modules\Contacts\Presentation\Queue\CallContactJob;

final class CallContactCommand extends ContactsCommand
{
    protected $signature = 'contacts:call {id} {--phone=} {--queue} {--json}';

    protected $description = 'Call a contact';

    /**
     * With --queue it first checks the contact exists, so an unknown id fails here instead of queueing a job that cannot succeed.
     */
    public function handle(
        CallContactDataFromCli $assembler,
        CallContact $action,
        GetContact $getContact,
    ): int {
        return $this->guarded(function () use ($assembler, $action, $getContact): int {
            $id = (string) $this->argument('id');
            $phone = $this->option('phone');
            $data = $assembler->assemble($id, is_string($phone) && $phone !== '' ? $phone : null);

            if ($this->option('queue')) {
                $getContact->handle($id);
                CallContactJob::dispatch($data);
                $this->info("Queued call to contact {$id}");

                return self::SUCCESS;
            }

            $result = $action->handle($data);

            if ($this->option('json')) {
                $this->printJson((new CallResource($result))->resolve());
            } else {
                $this->info("Called {$result->dialled}: {$result->outcome->value}");
            }

            return self::SUCCESS;
        });
    }
}
