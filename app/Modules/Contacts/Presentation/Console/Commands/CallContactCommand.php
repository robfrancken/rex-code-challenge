<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\Assemblers\CallContactDataFromCli;
use App\Modules\Contacts\Presentation\Http\Resources\CallResource;
use App\Modules\Contacts\Presentation\Queue\CallContactJob;

final class CallContactCommand extends ContactsCommand
{
    protected $signature = 'contacts:call {id} {--phone=} {--queue} {--json}';

    protected $description = 'Call a contact';

    public function handle(
        CallContactDataFromCli $assembler,
        CallContact $action,
    ): int {
        return $this->guarded(function () use ($assembler, $action): int {
            $id = (string) $this->argument('id');
            $phone = $this->option('phone');
            $data = $assembler->assemble($id, is_string($phone) && $phone !== '' ? $phone : null);

            if ($this->option('queue')) {
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
