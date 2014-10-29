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
 * The ROOT directory of the ICanBoogie framework.
 *
 * @var string
 */
defined('ICanBoogie\ROOT')
or define('ICanBoogie\ROOT', rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * Path to the ICanBoogie's assets directory.
 *
 * @var string
 */
define('ICanBoogie\ASSETS', ROOT . 'assets' . DIRECTORY_SEPARATOR);

/**
 * Document root of the application.
 *
 * We ensure that the directory separator is indeed the directory separator used by the file
 * system. e.g. "c:path/to/my/root" is changed to "c:path\to\my\root" if the directory
 * separator is "\".
 *
 * @var string
 */
defined('ICanBoogie\DOCUMENT_ROOT')
or define('ICanBoogie\DOCUMENT_ROOT', rtrim(strtr($_SERVER['DOCUMENT_ROOT'] ?: getcwd(), DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * Repository root. The repository is the directory where all files are stored. It's the only
 * directory that should be writable.
 *
 * @var string
 */
defined('ICanBoogie\REPOSITORY')
or define('ICanBoogie\REPOSITORY', DOCUMENT_ROOT . 'repository' . DIRECTORY_SEPARATOR);

/**
 * Pathname to the auto-config file.
 *
 *  @var string
 */
defined('ICanBoogie\AUTOCONFIG_PATHNAME')
or define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'auto-config.php');

/**
 * Returns the auto-config.
 *
 * The path of the auto-config is defined by the {@link AUTOCONFIG_PATHNAME} constant.
 */
function get_autoconfig()
{
	static $autoconfig;

	if ($autoconfig === null)
	{
		if (!file_exists(AUTOCONFIG_PATHNAME))
		{
			trigger_error("The auto-config file has not been generated. Check the `script` section of your composer.json file. https://github.com/ICanBoogie/ICanBoogie#generating-the-auto-config-file", E_USER_ERROR);
		}

		$autoconfig = require AUTOCONFIG_PATHNAME;

		foreach ($autoconfig['filters'] as $filter)
		{
			call_user_func_array($filter, [ &$autoconfig ]);
		}
	}

	return $autoconfig;
}

register_shutdown_function('ICanBoogie\Debug::shutdown_handler');

require_once ROOT . 'patches.php';
