<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Console\Commands;

use App\Exceptions\ApplicationException;
use App\Exceptions\ProblemException;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

abstract class ContactsCommand extends Command
{
    /**
     * Runs the operation, turning validation, application and problem exceptions into printed errors and exit codes.
     *
     * @param  callable(): int  $operation
     */
    protected function guarded(callable $operation): int
    {
        try {
            return $operation();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->error("{$field}: {$message}");
                }
            }

            return self::INVALID;
        } catch (ApplicationException|ProblemException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    protected function printJson(array $data): void
    {
        $this->line((string) json_encode($data));
    }
}
