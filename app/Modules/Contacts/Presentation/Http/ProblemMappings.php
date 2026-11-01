<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http;

use App\Http\Problems\ProblemRenderer;
use App\Modules\Contacts\Application\Exceptions\ContactNotFound;
use App\Modules\Contacts\Application\Exceptions\TelephonyFailed;

/**
 * Maps the module's transport-neutral application exceptions to HTTP problem responses.
 */
final class ProblemMappings
{
    public static function register(): void
    {
        ProblemRenderer::map(ContactNotFound::class, 404, 'Contact not found');
        ProblemRenderer::map(TelephonyFailed::class, 502, 'Call could not be placed');
    }
}
