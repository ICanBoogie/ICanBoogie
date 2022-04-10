<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Autoconfig;

/**
 * Keys of the ICanBoogie section in the composer.json file.
 *
 * @see schema.json
 */
interface SchemaOptions
{
    public const AUTOCONFIG_EXTENSION = 'autoconfig-extension';
    public const AUTOCONFIG_FILTERS = 'autoconfig-filters';
    public const CONFIG_CONSTRUCTOR = 'config-constructor';
    public const CONFIG_PATH = 'config-path';
    public const CONFIG_WEIGHT = 'config-weight';
    public const LOCALE_PATH = 'locale-path';
    public const APP_PATH = 'app-path';
    public const APP_PATHS = 'app-paths';
}
