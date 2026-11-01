<?php

declare(strict_types=1);

namespace App\Http\Problems;

use Illuminate\Http\JsonResponse;

final class ProblemDetails
{
    /**
     * @param  array<string, mixed>  $extensions
     * @param  array<string, string>  $headers
     */
    public static function response(
        int $status,
        string $code,
        string $title,
        string $detail,
        array $extensions = [],
        array $headers = [],
    ): JsonResponse {
        $body = [
            'type' => "/problems/{$code}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'code' => $code,
            ...$extensions,
        ];

        return new JsonResponse(
            $body,
            $status,
            [...$headers, 'Content-Type' => 'application/problem+json'],
        );
    }
}
