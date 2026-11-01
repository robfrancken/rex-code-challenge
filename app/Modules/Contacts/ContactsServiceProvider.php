<?php

declare(strict_types=1);

namespace App\Modules\Contacts;

use App\Modules\Contacts\Domain\Telephony\TelephonyGateway;
use App\Modules\Contacts\Infrastructure\FakeTelephonyGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ContactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TelephonyGateway::class, FakeTelephonyGateway::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        Route::middleware(['web', 'auth', 'throttle:api'])
            ->prefix('api/v1')
            ->name('contacts.')
            ->group(__DIR__.'/Presentation/Http/routes.php');
    }
}
