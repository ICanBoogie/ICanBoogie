<?php

namespace ICanBoogie;

use ICanBoogie\Autoconfig\Autoconfig;

use function implode;

use const DIRECTORY_SEPARATOR;

/*
 * Application
 */

/**
 * Instantiate and boot the application.
 *
 * @param Autoconfig|null $autoconfig
 *     If `null`, the config is obtained with {@see Autoconfig::get()}.
 */
function boot(Autoconfig $autoconfig = null): Application
{
    $autoconfig ??= Autoconfig::get();
    $app = Application::new($autoconfig);
    $app->boot();

    return $app;
}

/**
 * Returns the {@link Application} instance.
 */
function app(): Application
{
    static $app;

    return $app ??= Application::get();
}
