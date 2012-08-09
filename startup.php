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
 * Version string of the ICanBoogie framework.
 *
 * @var string
 */
const VERSION = '0.20.0-dev (2012-07-09)';

/**
 * The ROOT directory of the ICanBoogie framework.
 *
 * @var string
 */
defined('ICanBoogie\ROOT') or define('ICanBoogie\ROOT', rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

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
defined('ICanBoogie\DOCUMENT_ROOT') or define('ICanBoogie\DOCUMENT_ROOT', rtrim(strtr($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * Repository root. The repository is the directory where all files are stored. It's the only
 * directory that should be writable.
 *
 * @var string
 */

defined('ICanBoogie\REPOSITORY') or define('ICanBoogie\REPOSITORY', DOCUMENT_ROOT . 'repository' . DIRECTORY_SEPARATOR);

/**
 * If true, APC is used to store and retrieve active records.
 *
 * @var bool
 */
defined('ICanBoogie\CACHE_ACTIVERECORDS') or define('ICanBoogie\CACHE_ACTIVERECORDS', false);

/**
 * The charset used by the application. Defaults to "utf-8".
 *
 * @var string
 */
defined('ICanBoogie\CHARSET') or define('ICanBoogie\CHARSET', 'utf-8');

if (function_exists('mb_internal_encoding'))
{
	mb_internal_encoding(CHARSET);
}

/**
 * Enables bootstrap caching.
 *
 * @var bool
 */
defined('ICanBoogie\CACHE_BOOTSTRAP') or define('ICanBoogie\CACHE_BOOTSTRAP', false);

/**
 * Bootstrap cache pathname.
 *
 * @var string
 */
defined('ICanBoogie\BOOTSTRAP_CACHE_PATHNAME') or define('ICanBoogie\BOOTSTRAP_CACHE_PATHNAME', REPOSITORY . 'cache' . DIRECTORY_SEPARATOR . 'icanboogie_bootstrap');

/*
 * Define PHP5.4 `$_SERVER['REQUEST_TIME_FLOAT']` if empty.
 */
if (empty($_SERVER['REQUEST_TIME_FLOAT']))
{
	$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}

/*
 * bootstrap
 */
require_once ROOT . 'lib/helpers.php';
require_once ROOT . 'lib/http/helpers.php';
require_once ROOT . 'lib/i18n/helpers.php';

if (CACHE_BOOTSTRAP && file_exists(BOOTSTRAP_CACHE_PATHNAME))
{
	require_once BOOTSTRAP_CACHE_PATHNAME;
}
else
{
	require_once ROOT . 'lib/core/debug.php';
	require_once ROOT . 'lib/core/exception.php';
	require_once ROOT . 'lib/core/event.php';
	require_once ROOT . 'lib/core/prototype.php';
	require_once ROOT . 'lib/core/object.php';
	require_once ROOT . 'lib/core/accessors/configs.php';
	require_once ROOT . 'lib/core/core.php';
}