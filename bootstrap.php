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
 */
defined('ICanBoogie\ROOT')
or define('ICanBoogie\ROOT', rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

/**
 * Path to the ICanBoogie's assets directory.
 */
const ASSETS = ROOT . 'assets' . DIRECTORY_SEPARATOR;

/**
 * Document root of the application.
 *
 * We ensure that the directory separator is indeed the directory separator used by the file
 * system. e.g. "c:path/to/my/root" is changed to "c:path\to\my\root" if the directory
 * separator is "\".
 *
 * @deprecated
 */
defined('ICanBoogie\DOCUMENT_ROOT')
or define('ICanBoogie\DOCUMENT_ROOT', rtrim(strtr($_SERVER['DOCUMENT_ROOT'] ?: getcwd(), DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
