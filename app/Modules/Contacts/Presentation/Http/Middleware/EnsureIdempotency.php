<?php

declare(strict_types=1);

namespace App\Modules\Contacts\Presentation\Http\Middleware;

use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyInvalid;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyMissing;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyKeyReused;
use App\Modules\Contacts\Presentation\Http\Exceptions\IdempotencyRequestInProgress;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class EnsureIdempotency
{
    private const int MAX_KEY_LENGTH = 255;

    /**
     * Must outlive the slowest gateway call, otherwise the lock could expire mid-request and allow a double dial.
     */
    private const int LOCK_SECONDS = 30;

    private const int LOCK_WAIT_SECONDS = 5;

    private const int TTL_SECONDS = 86400;

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws IdempotencyKeyMissing
     * @throws IdempotencyKeyInvalid
     * @throws IdempotencyRequestInProgress
     * @throws IdempotencyKeyReused
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || $key === '') {
            throw IdempotencyKeyMissing::make();
        }

        if (strlen($key) > self::MAX_KEY_LENGTH) {
            throw IdempotencyKeyInvalid::make();
        }

        $cacheKey = sprintf(
            'idempotency:%s:%s:%s',
            $request->user()?->getAuthIdentifier(),
            $request->route()?->getName(),
            hash('sha256', $key),
        );
        $fingerprint = hash('sha256', $request->method().$request->path().$request->getContent());

        try {
            return Cache::lock($cacheKey.':lock', self::LOCK_SECONDS)->block(
                self::LOCK_WAIT_SECONDS,
                fn (): Response => $this->handleLocked($request, $next, $cacheKey, $fingerprint),
            );
        } catch (LockTimeoutException) {
            throw IdempotencyRequestInProgress::make();
        }
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    private function handleLocked(
        Request $request,
        Closure $next,
        string $cacheKey,
        string $fingerprint,
    ): Response {
        /** @var array{fingerprint: string, status: int, body: string, content_type: string}|null $stored */
        $stored = Cache::get($cacheKey);

        if ($stored !== null) {
            if ($stored['fingerprint'] !== $fingerprint) {
                throw IdempotencyKeyReused::make();
            }

            return response($stored['body'], $stored['status'], [
                'Content-Type' => $stored['content_type'],
                'Idempotency-Replayed' => 'true',
            ]);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            Cache::put($cacheKey, [
                'fingerprint' => $fingerprint,
                'status' => $response->getStatusCode(),
                'body' => (string) $response->getContent(),
                'content_type' => (string) $response->headers->get('Content-Type'),
            ], self::TTL_SECONDS);
        }

        return $response;
    }
}
