<?php

use App\Exceptions\ApplicationException;
use App\Exceptions\ProblemException;
use App\Http\Problems\ProblemRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport([
            ProblemException::class,
            ApplicationException::class,
        ]);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'login', 'logout') || $request->expectsJson(),
        );
        $exceptions->render(
            fn (Throwable $e, Request $request) => $request->is('api/*', 'login', 'logout') || $request->expectsJson()
                ? ProblemRenderer::render($e)
                : null,
        );
    })->create();
