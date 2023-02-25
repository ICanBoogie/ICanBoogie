<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\Debug\Config;

/**
 * @codeCoverageIgnore
 */
class Debug
{
    public const MODE_DEV = 'dev';
    public const MODE_STAGE = 'stage';
    public const MODE_PRODUCTION = 'production';

    public static $mode = 'dev';

    public static function is_dev(): bool
    {
        return self::$mode == self::MODE_DEV;
    }

    public static function is_stage(): bool
    {
        return self::$mode == self::MODE_STAGE;
    }

    public static function is_production(): bool
    {
        return self::$mode == self::MODE_PRODUCTION;
    }

    /**
     * Configures the class.
     */
    public static function configure(ConfigProvider $config_provider): void
    {
        $config = $config_provider->config_for_class(Config::class);

        self::$mode = $config->mode;
    }

    /*
    **

    DEBUG & TRACE

    **
    */

    private static function get_logger(): LoggerInterface
    {
        return app()->logger;
    }

    /**
     * The method is forwarded to the application's logger `get_messages()` method.
     *
     * @param $level
     *
     * @return \string[]
     */
    public static function get_messages($level)
    {
        return self::get_logger()->get_messages($level);
    }

    /**
     * The method is forwarded to the application's logger `fetch_messages()` method.
     *
     * @param $level
     *
     * @return \string[]
     */
    public static function fetch_messages($level)
    {
        return self::get_logger()->fetch_messages($level);
    }
}
