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

/**
 * @codeCoverageIgnore
 */
class Debug
{
    public const MODE_DEV = 'dev';
    public const MODE_STAGE = 'stage';
    public const MODE_PRODUCTION = 'production';

    public static $mode = 'dev';

    /**
     * @param array<string, mixed>[] $fragments
     *
     * @return array<string, mixed>
     */
    public static function synthesize_config(array $fragments): array
    {
        $config = array_merge_recursive(...array_values($fragments));
        $config = array_merge($config, $config['modes'][$config['mode']]);

        return $config;
    }

    private static $config;

    private static $config_code_sample = true;
    private static $config_line_number = true;
    private static $config_stack_trace = true;
    private static $config_exception_chain = true;
    private static $config_verbose = true;

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
     *
     * @param array $config A config such as one returned by `$app->configs['debug']`.
     */
    public static function configure(array $config)
    {
        $mode = self::$mode;
        $modes = [];

        foreach ($config as $directive => $value) {
            if ($directive == 'mode') {
                $mode = $value;

                continue;
            } elseif ($directive == 'modes') {
                $modes = $value;

                continue;
            }

            $directive = 'config_' . $directive;

            self::$$directive = $value;
        }

        self::$mode = $mode;

        if (isset($modes[$mode])) {
            foreach ($modes[$mode] as $directive => $value) {
                $directive = 'config_' . $directive;

                self::$$directive = $value;
            }
        }
    }

    /*
    **

    DEBUG & TRACE

    **
    */

    private static function get_logger()
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
