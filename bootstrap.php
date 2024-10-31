<?php

namespace ICanBoogie;

/**
 * The ROOT directory of the ICanBoogie framework.
 */
defined('ICanBoogie\ROOT')
or define('ICanBoogie\ROOT', rtrim(__DIR__, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

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
