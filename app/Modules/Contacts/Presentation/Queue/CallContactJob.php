<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Queue;

use App\Modules\Contacts\Application\Actions\CallContact;
use App\Modules\Contacts\Application\DTOs\CallContactData;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class CallContactJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public CallContactData $data) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * @throws TelephonyFailed
     */
    public function handle(CallContact $action): void
    {
        $result = $action->handle($this->data);

        Log::info('contacts.call', [
            'contact_id' => $result->contactId,
            'outcome' => $result->outcome->value,
        ]);
    }
}
