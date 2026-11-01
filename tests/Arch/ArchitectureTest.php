<?php

arch()->preset()->php();
arch()->preset()->security();

foreach (glob(__DIR__.'/../../app/Modules/*', GLOB_ONLYDIR) ?: [] as $modulePath) {
    $module = 'App\\Modules\\'.basename($modulePath);

    arch("{$module} domain does not depend on outer layers")
        ->expect("{$module}\\Domain")
        ->not->toUse(["{$module}\\Application", "{$module}\\Presentation"]);

    arch("{$module} application does not depend on presentation")
        ->expect("{$module}\\Application")
        ->not->toUse("{$module}\\Presentation");

    arch("{$module} inner layers do not depend on infrastructure")
        ->expect(["{$module}\\Domain", "{$module}\\Application"])
        ->not->toUse("{$module}\\Infrastructure");
}
