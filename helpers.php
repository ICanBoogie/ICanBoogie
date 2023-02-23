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

use ICanBoogie\Autoconfig\Autoconfig;

use function defined;
use function dirname;
use function file_exists;
use function implode;

use const DIRECTORY_SEPARATOR;

/*
 * Application
 */

/**
 * Resolves application instance name.
 */
function resolve_instance_name(): string
{
    $instance = getenv('ICANBOOGIE_INSTANCE');

    if (!$instance && PHP_SAPI == 'cli') {
        $instance = 'cli';
    }

    if (!$instance && !empty($_SERVER['SERVER_NAME'])) {
        $instance = $_SERVER['SERVER_NAME'];
    }

    return $instance;
}

/**
 * Resolves the paths where the application can look for config, locale, modules, and more.
 *
 * @return string[] An array of absolute paths, ordered from the less specific to
 * the most specific.
 *
 * @see https://icanboogie.org/docs/4.0/multi-site
 */
function resolve_app_paths(string $root, string $instance = null): array
{
    static $cache = [];

    $instance ??= resolve_instance_name();

    $cache_key = $root . '#' . $instance;

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $parts = explode('.', $instance);
    $paths = [];

    while ($parts) {
        $try = $root . implode('.', $parts);

        if (!file_exists($try)) {
            array_shift($parts);


            continue;
        }

        $paths[] = $try . DIRECTORY_SEPARATOR;

        break;
    }

    if (!$paths && file_exists($root . 'default')) {
        $paths[] = $root . 'default' . DIRECTORY_SEPARATOR;
    }

    if (file_exists($root . 'all')) {
        array_unshift($paths, $root . 'all' . DIRECTORY_SEPARATOR);
    }

    $cache[$cache_key] = $paths;

    return $paths;
}

/**
 * Returns the autoconfig.
 *
 * @return array<Autoconfig::*, mixed>
 *
 * @see https://icanboogie.org/docs/4.0/autoconfig#obtaining-the-autoconfig
 */
function get_autoconfig(): array
{
    static $autoconfig;

    if ($autoconfig) {
        return $autoconfig;
    }

    if (!defined('ICANBOOGIE_AUTOCONFIG')) {
        $tries = [
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'autoconfig.php',
            __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'icanboogie' . DIRECTORY_SEPARATOR . 'autoconfig.php',
        ];

        foreach ($tries as $try) {
            if (file_exists($try)) {
                define('ICANBOOGIE_AUTOCONFIG', $try);
                break;
            }
        }

        if (!defined('ICANBOOGIE_AUTOCONFIG')) {
            $tries = implode(', ', $tries);

            trigger_error(
                "The autoconfig file is missing, tried: $tries. Check the `script` section of your composer.json file. https://icanboogie.org/docs/4.0/autoconfig#generating-the-autoconfig-file",
                E_USER_ERROR
            );
        }
    }

    $autoconfig = require \ICANBOOGIE_AUTOCONFIG;
    $autoconfig[Autoconfig::APP_PATHS] = array_merge(
        $autoconfig[Autoconfig::APP_PATHS],
        resolve_app_paths($autoconfig[Autoconfig::APP_PATH])
    );

    foreach ($autoconfig[Autoconfig::AUTOCONFIG_FILTERS] as $filter) {
        call_user_func_array($filter, [ &$autoconfig ]);
    }

    return $autoconfig;
}

/**
 * Instantiate and boot the application.
 *
 * @param array<string, mixed>|null $options If `null` options are obtained with `get_autoconfig()`.
 */
function boot(array $options = null): Application
{
    $options ??= get_autoconfig();
    $app = new Application($options);
    $app->boot();

    return $app;
}

/**
 * Return application instance.
 *
 * @throws ApplicationNotInstantiated if the application has not been instantiated yet.
 */
function app(): Application
{
    return Application::get()
        ?? throw new ApplicationNotInstantiated();
}

/*
 * Logger
 */

/**
 * Logs a debug message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 * @param string $level
 */
function log($message, array $params = [], $level = LogLevel::DEBUG)
{
    static $logger;

    if (!$logger) {
        $logger = app()->logger;
    }

    $logger->{$level}($message, $params);
}

/**
 * Logs a success message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_success($message, array $params = [])
{
    log($message, $params, LogLevel::SUCCESS);
}

/**
 * Logs an error message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_error($message, array $params = [])
{
    log($message, $params, LogLevel::ERROR);
}

/**
 * Logs an info message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_info($message, array $params = [])
{
    log($message, $params, LogLevel::INFO);
}

/**
 * Logs a debug message associated with a timing information.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_time($message, array $params = [])
{
    static $last;

    $now = microtime(true);

    $add = '<var>[';

    $add .= '∑' . number_format($now - $_SERVER['REQUEST_TIME_FLOAT'], 3, '\'', '') . '"';

    if ($last) {
        $add .= ', +' . number_format($now - $last, 3, '\'', '') . '"';
    }

    $add .= ']</var>';

    $last = $now;

    $message = $add . ' ' . $message;

    log($message, $params);
}

/*
 * Utils
 */

const TOKEN_NUMERIC = "23456789";
const TOKEN_ALPHA = "abcdefghjkmnpqrstuvwxyz";
const TOKEN_ALPHA_UPCASE = "ABCDEFGHJKLMNPQRTUVWXYZ";
const TOKEN_SYMBOL = "!$=@#";
const TOKEN_SYMBOL_WIDE = '%&()*+,-./:;<>?@[]^_`{|}~';

define('ICanBoogie\TOKEN_NARROW', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL);
define('ICanBoogie\TOKEN_MEDIUM', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL . TOKEN_ALPHA_UPCASE);
define('ICanBoogie\TOKEN_WIDE', TOKEN_NUMERIC . TOKEN_ALPHA . TOKEN_SYMBOL . TOKEN_ALPHA_UPCASE . TOKEN_SYMBOL_WIDE);

/**
 * Generate a password.
 *
 * @param int $length The length of the password. Default: 8
 * @param string $possible The characters that can be used to create the password.
 * If you defined your own, pay attention to ambiguous characters such as 0, O, 1, l, I...
 * Default: {@link TOKEN_NARROW}
 *
 * @return string
 */
function generate_token($length = 8, $possible = TOKEN_NARROW)
{
    return Helpers::generate_token($length, $possible);
}

/**
 * Generate a 512 bit (64 chars) length token from {@link TOKEN_WIDE}.
 *
 * @return string
 */
function generate_token_wide()
{
    return Helpers::generate_token(64, TOKEN_WIDE);
}

/**
 * Creates an excerpt of an HTML string.
 *
 * The following tags are preserved: A, P, CODE, DEL, EM, INS and STRONG.
 *
 * @param string $str HTML string.
 * @param int $limit The maximum number of words.
 *
 * @return string
 */
function excerpt($str, $limit = 55)
{
    static $allowed_tags = [

        'a',
        'p',
        'code',
        'del',
        'em',
        'ins',
        'strong'

    ];

    $str = strip_tags(trim($str), '<' . implode('><', $allowed_tags) . '>');
    $str = preg_replace('#(<p>|<p\s+[^\>]+>)\s*</p>#', '', $str);

    $parts = preg_split('#<([^\s>]+)([^>]*)>#m', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

    # i+0: text
    # i+1: markup ('/' prefix for closing markups)
    # i+2: markup attributes

    $rc = '';
    $opened = [];

    foreach ($parts as $i => $part) {
        if ($i % 3 == 0) {
            $words = preg_split('#(\s+)#', $part, 0, PREG_SPLIT_DELIM_CAPTURE);

            foreach ($words as $w => $word) {
                if ($w % 2 == 0) {
                    if (!$word) // TODO-20100908: strip punctuation
                    {
                        continue;
                    }

                    $rc .= $word;

                    if (!--$limit) {
                        break;
                    }
                } else {
                    $rc .= $word;
                }
            }

            if (!$limit) {
                break;
            }
        } else {
            if ($i % 3 == 1) {
                if ($part[0] == '/') {
                    $rc .= '<' . $part . '>';

                    array_shift($opened);
                } else {
                    array_unshift($opened, $part);

                    $rc .= '<' . $part . $parts[$i + 1] . '>';
                }
            }
        }
    }

    if (!$limit) {
        $rc .= ' <span class="excerpt-warp">[…]</span>';
    }

    if ($opened) {
        $rc .= '</' . implode('></', $opened) . '>';
    }

    return $rc;
}

/**
 * Removes the `DOCUMENT_ROOT` from the provided path.
 *
 * Note: Because this function is usually used to create URL path from server path, the directory
 * separator '\' is replaced by '/'.
 *
 * @param string $pathname
 *
 * @return string
 */
function strip_root($pathname)
{
    $root = rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR);
    $root = strtr($root, DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR);
    $pathname = strtr($pathname, DIRECTORY_SEPARATOR == '/' ? '\\' : '/', DIRECTORY_SEPARATOR);

    if ($root && strpos($pathname, $root) === 0) {
        $pathname = substr($pathname, strlen($root));
    }

    if (DIRECTORY_SEPARATOR != '/') {
        $pathname = strtr($pathname, DIRECTORY_SEPARATOR, '/');
    }

    return $pathname;
}
