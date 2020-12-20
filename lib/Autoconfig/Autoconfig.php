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
	public const APP_PATH = 'app-path';
	public const APP_PATHS = 'app-paths';
	public const AUTOCONFIG_FILTERS = 'autoconfig-filters';

	/**
	 * The fully qualified path to the project root.
	 */
	public const BASE_PATH = 'base-path';
	public const CONFIG_CONSTRUCTOR = 'config-constructor';
	public const CONFIG_PATH = 'config-path';
	public const CONFIG_WEIGHT = 'config-weight';
	public const CONFIG_WEIGHT_FRAMEWORK = -100;
	public const CONFIG_WEIGHT_MODULE = 0;
	public const CONFIG_WEIGHT_APP = 100;
	public const LOCALE_PATH = 'locale-path';
	public const ROOT = 'root';

	public const DEFAULT_APP_DIRECTORY = 'app';
}
