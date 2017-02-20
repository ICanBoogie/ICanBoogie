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

interface Autoconfig
{
	/**
	 * The fully qualified path to the `app` directory.
	 */
	const APP_PATH = 'app-path';
	const APP_PATHS = 'app-paths';
	const AUTOCONFIG_FILTERS = 'autoconfig-filters';

	/**
	 * The fully qualified path to the project root.
	 */
	const BASE_PATH = 'base-path';
	const CONFIG_CONSTRUCTOR = 'config-constructor';
	const CONFIG_PATH = 'config-path';
	const LOCALE_PATH = 'locale-path';
	const MODULE_PATH = 'module-path';
	const CONFIG_WEIGHT = 'config-weight';
	const CONFIG_WEIGHT_FRAMEWORK = -100;
	const CONFIG_WEIGHT_MODULE = 0;
	const CONFIG_WEIGHT_APP = 100;
	const ROOT = 'root';

	const DEFAULT_APP_DIRECTORY = 'app';
}
