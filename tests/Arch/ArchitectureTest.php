<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->security();

arch('every app file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

foreach (glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
    $module = 'App\\Modules\\'.basename($modulePath);

    arch("{$module} domain does not depend on outer layers")
        ->expect("{$module}\\Domain")
        ->not->toUse(["{$module}\\Application", "{$module}\\Presentation"]);

    arch("{$module} application does not depend on presentation")
        ->expect("{$module}\\Application")
        ->not->toUse("{$module}\\Presentation");

    arch("{$module} application is transport-neutral")
        ->expect("{$module}\\Application")
        ->not->toUse(['App\\Http', 'App\\Exceptions\\ProblemException']);

    arch("{$module} dtos and value objects are final and readonly")
        ->expect(["{$module}\\Application\\DTOs", "{$module}\\Domain\\ValueObjects"])
        ->toBeFinal()
        ->toBeReadonly();

    arch("{$module} inner layers do not depend on infrastructure")
        ->expect(["{$module}\\Domain", "{$module}\\Application"])
        ->not->toUse("{$module}\\Infrastructure");
}
