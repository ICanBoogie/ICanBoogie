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
 * @var string Version string of the ICanBoogie framework.
 */
define('ICanBoogie\VERSION', '0.13.0-dev (2012-01-15)');

/**
 * @var string Document root of the application.
 */
defined('ICanBoogie\DOCUMENT_ROOT') or define('ICanBoogie\DOCUMENT_ROOT', rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * @var string The ROOT directory of the ICanBoogie framework.
 */
defined('ICanBoogie\ROOT') or define('ICanBoogie\ROOT', rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * @var string Path to the ICanBoogie's assets directory.
 */
define('ICanBoogie\ASSETS', ROOT . 'assets' . DIRECTORY_SEPARATOR);

/**
 * @var bool If true, an APC cache is used to store and retrieve active records.
 */
define('ICanBoogie\CACHE_ACTIVERECORDS', false);

/**
 * @var string The charset used by the application. Defaults to "utf-8".
 */
defined('ICanBoogie\CHARSET') or define('ICanBoogie\CHARSET', 'utf-8');

require_once ROOT . 'lib/helpers.php';
require_once ROOT . 'lib/toolkit/helpers.php';
require_once ROOT . 'lib/i18n/helpers.php';

if (file_exists(DOCUMENT_ROOT . 'repository/cache/icanboogie_bootstrap'))
{
	require_once DOCUMENT_ROOT . 'repository/cache/icanboogie_bootstrap';
}
else
{
	require_once ROOT . 'lib/core/debug.php';
	require_once ROOT . 'lib/core/exception.php';
	require_once ROOT . 'lib/core/object.php';
	require_once ROOT . 'lib/core/accessors/configs.php';
	require_once ROOT . 'lib/core/core.php';
}