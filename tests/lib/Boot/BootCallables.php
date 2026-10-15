<?php

namespace Test\ICanBoogie\Boot;

use ICanBoogie\Application;
use ICanBoogie\Application\BootEvent;

/**
 * Fixture recording the order in which boot callables and the {@see BootEvent} are invoked.
 */
final class BootCallables
{
    /**
     * @var list<string>
     */
    public static array $log = [];

    public static function first(Application $app): void
    {
        self::$log[] = $app->is_booting ? 'first:booting' : 'first:not-booting';
    }

    public static function second(Application $app): void
    {
        self::$log[] = 'second';
    }

    public static function on_boot(BootEvent $event): void
    {
        self::$log[] = 'event';
    }
}
