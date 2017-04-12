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
 * @see composer-schema.json
 */
interface ComposerExtra
{
	const AUTOCONFIG_EXTENSION = 'autoconfig-extension';
	const AUTOCONFIG_FILTERS = 'autoconfig-filters';
	const CONFIG_CONSTRUCTOR = 'config-constructor';
	const CONFIG_PATH = 'config-path';
	const CONFIG_WEIGHT = 'config-weight';
	const LOCALE_PATH = 'locale-path';
	const MODULE_PATH = 'module-path';
	const APP_PATH = 'app-path';
	const APP_PATHS = 'app-paths';
}
