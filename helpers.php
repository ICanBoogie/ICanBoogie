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

/*
 * Core
 */

/**
 * Resolves the paths where the application can look for config, locale, modules, and more.
 *
 * Consider an application root directory  with the following directories:
 *
 * <pre>
 * all
 * cli
 * default
 * fr
 * icanboogie.fr
 * localhost
 * org
 * </pre>
 *
 * The directory "all" contains resources that are shared between all the sites. It is always
 * added if it is present. The directory "default" is only used if a directory matching
 * `$server_name` cannot be found. The directory "cli" is used when the application is ran
 * from the CLI.
 *
 * To resolve the matching directory, `$server_name` is first broken into parts and the most
 * specific ones are removed until a corresponding directory is found. For instance, given
 * the server name "www.icanboogie.localhost", the following directories are tried:
 * "www.icanboogie.localhost", "icanboogie.localhost", and finally "localhost".
 *
 * @param string $root The absolute path of a root directory.
 * @param string|null $server_name A server name. If `$server_name` is `null`, it is resolved from
 * `PHP_SAPI` and `$_SERVER['SERVER_NAME']`. If `PHP_SAPI` equals "cli", then "cli" is used,
 * otherwise `$_SERVER['SERVER_NAME']` is used.
 *
 * @return string[] An array of absolute paths, ordered from the less specific to
 * the most specific.
 */
function resolve_app_paths($root, $server_name=null)
{
	static $cache = [];

	if ($server_name === null)
	{
		$server_name = PHP_SAPI == 'cli' ? 'cli' : (empty($_SERVER['SERVER_NAME']) ? null : $_SERVER['SERVER_NAME']);
	}

	$cache_key = $root . '#' . $server_name;

	if (isset($cache[$cache_key]))
	{
		return $cache[$cache_key];
	}

	$root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
	$parts = explode('.', $server_name);
	$paths = [];

	while ($parts)
	{
		$try = $root . implode('.', $parts);

		if (!file_exists($try))
		{
			array_shift($parts);


			continue;
		}

		$paths[] = $try . DIRECTORY_SEPARATOR;

		break;
	}

	if (!$paths && file_exists($root . 'default'))
	{
		$paths[] = $root . 'default' . DIRECTORY_SEPARATOR;
	}

	if (file_exists($root . 'all'))
	{
		array_unshift($paths, $root . 'all' . DIRECTORY_SEPARATOR);
	}

	$cache[$cache_key] = $paths;

	return $paths;
}

/**
 * Returns the autoconfig.
 *
 * The path of the autoconfig is defined by the {@link AUTOCONFIG_PATHNAME} constant.
 *
 * The `app-root` and `app-paths` values are updated. `app-root` is resolved from `root`, which may
 * gives `false` if the application root is not defined. The value `app-paths` is returned by
 * the {@link resolve_app_paths()} function with `app-root` as parameter.
 *
 * The filters defined in `filters` are invoked to alter the autoconfig.
 *
 * @return array
 */
function get_autoconfig()
{
	static $autoconfig;

	if ($autoconfig === null)
	{
		if (!file_exists(AUTOCONFIG_PATHNAME))
		{
			trigger_error("The autoconfig file has not been generated. Check the `script` section of your composer.json file. https://github.com/ICanBoogie/ICanBoogie#generating-the-autoconfig-file", E_USER_ERROR);
		}

		$autoconfig = (require AUTOCONFIG_PATHNAME) + [

			'app-root' => 'protected'

		];

		$root = $autoconfig['root'];
		$autoconfig['app-root'] = realpath($root . DIRECTORY_SEPARATOR . $autoconfig['app-root']);
		$autoconfig['app-paths'] = array_merge($autoconfig['app-paths'], resolve_app_paths($autoconfig['app-root']));

		foreach ($autoconfig['filters'] as $filter)
		{
			call_user_func_array($filter, [ &$autoconfig ]);
		}
	}

	return $autoconfig;
}

/**
 * Instantiates a {@link Core} instance with the autoconfig and boots it.
 *
 * @return Core
 */
function boot()
{
	$core = new Core( get_autoconfig() );
	$core->boot();

	return $core;
}

/**
 * Returns the {@link Core} instance.
 *
 * @return Core The {@link Core} instance.
 *
 * @throws CoreNotInstantiated if the core has not been instantiated yet.
 */
function app()
{
	$core = Core::get();

	if (!$core)
	{
		throw new CoreNotInstantiated;
	}

	return $core;
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
function log($message, array $params=[], $level=LogLevel::DEBUG)
{
	static $logger;

	if (!$logger)
	{
		$logger = app()->logger;
	}

	$logger->{ $level }($message, $params);
}

/**
 * Logs a success message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_success($message, array $params=[])
{
	log($message, $params, LogLevel::SUCCESS);
}

/**
 * Logs an error message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_error($message, array $params=[])
{
	log($message, $params, LogLevel::ERROR);
}

/**
 * Logs an info message.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_info($message, array $params=[])
{
	log($message, $params, LogLevel::INFO);
}

/**
 * Logs a debug message associated with a timing information.
 *
 * @param string $message Message pattern.
 * @param array $params The parameters used to format the message.
 */
function log_time($message, array $params=[])
{
	static $last;

	$now = microtime(true);

	$add = '<var>[';

	$add .= '∑' . number_format($now - $_SERVER['REQUEST_TIME_FLOAT'], 3, '\'', '') . '"';

	if ($last)
	{
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
function generate_token($length=8, $possible=TOKEN_NARROW)
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

/** PBKDF2 Implementation (described in RFC 2898)
 *
 *  @param string $p password
 *  @param string $s salt
 *  @param int $c iteration count (use 1000 or higher)
 *  @param int $kl derived key length
 *  @param string $a hash algorithm
 *
 *  @return string derived key
 *
 *  @source http://www.itnewb.com/v/Encrypting-Passwords-with-PHP-for-Storage-Using-the-RSA-PBKDF2-Standard
 */
function pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256')
{
	return Helpers::pbkdf2($p, $s, $c, $kl, $a);
}

/**
 * Normalize a string to be suitable as a namespace part.
 *
 * @param string $part The string to normalize.
 *
 * @return string Normalized string.
 */
function normalize_namespace_part($part)
{
	return preg_replace_callback
	(
		'/[-\s_\.]\D/', function ($match)
		{
			$rc = ucfirst($match[0]{1});

			if ($match[0]{0} == '.')
			{
				$rc = '\\' . $rc;
			}

			return $rc;
		},

		' ' . $part
	);
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
function excerpt($str, $limit=55)
{
	static $allowed_tags = [

		'a', 'p', 'code', 'del', 'em', 'ins', 'strong'

	];

	$str = strip_tags(trim($str), '<' . implode('><', $allowed_tags) . '>');
	$str = preg_replace('#(<p>|<p\s+[^\>]+>)\s*</p>#', '', $str);

	$parts = preg_split('#<([^\s>]+)([^>]*)>#m', $str, 0, PREG_SPLIT_DELIM_CAPTURE);

	# i+0: text
	# i+1: markup ('/' prefix for closing markups)
	# i+2: markup attributes

	$rc = '';
	$opened = [];

	foreach ($parts as $i => $part)
	{
		if ($i % 3 == 0)
		{
			$words = preg_split('#(\s+)#', $part, 0, PREG_SPLIT_DELIM_CAPTURE);

			foreach ($words as $w => $word)
			{
				if ($w % 2 == 0)
				{
					if (!$word) // TODO-20100908: strip punctuation
					{
						continue;
					}

					$rc .= $word;

					if (!--$limit)
					{
						break;
					}
				}
				else
				{
					$rc .= $word;
				}
			}

			if (!$limit)
			{
				break;
			}
		}
		else if ($i % 3 == 1)
		{
			if ($part[0] == '/')
			{
				$rc .= '<' . $part . '>';

				array_shift($opened);
			}
			else
			{
				array_unshift($opened, $part);

				$rc .= '<' . $part . $parts[$i + 1] . '>';
			}
		}
	}

	if (!$limit)
	{
		$rc .= ' <span class="excerpt-warp">[…]</span>';
	}

	if ($opened)
	{
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

	if ($root && strpos($pathname, $root) === 0)
	{
		$pathname = substr($pathname, strlen($root));
	}

	if (DIRECTORY_SEPARATOR != '/')
	{
		$pathname = strtr($pathname, DIRECTORY_SEPARATOR, '/');
	}

	return $pathname;
}
