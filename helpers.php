<?php

namespace ICanBoogie;

use ICanBoogie\Application\InvalidState;
use ICanBoogie\Autoconfig\Autoconfig;

/**
 * Instantiate and boot the application.
 *
 * @param Autoconfig|null $autoconfig
 *     If `null`, the config is obtained with {@see Autoconfig::get()}.
 */
function boot(?Autoconfig $autoconfig = null): Application
{
    $autoconfig ??= Autoconfig::get();
    $app = Application::new($autoconfig);
    $app->boot();

    return $app;
}

/**
 * Returns the {@see Application} instance.
 *
 * @throws InvalidState if the application is not ready.
 */
function app(): Application
{
    static $app;

    return $app ??= Application::get();
}
