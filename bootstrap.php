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
 * The application root directory.
 *
 * @var string
 */
defined('ICanBoogie\APP_ROOT')
or define('ICanBoogie\APP_ROOT', getcwd() . DIRECTORY_SEPARATOR);

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
 *
 * @deprecated
 */
defined('ICanBoogie\DOCUMENT_ROOT')
or define('ICanBoogie\DOCUMENT_ROOT', rtrim(strtr($_SERVER['DOCUMENT_ROOT'] ?: getcwd(), DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * Pathname to the autoconfig file.
 *
 *  @var string
 */
defined('ICanBoogie\AUTOCONFIG_PATHNAME')
or define('ICanBoogie\AUTOCONFIG_PATHNAME', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoconfig.php');

register_shutdown_function('ICanBoogie\Debug::shutdown_handler');
