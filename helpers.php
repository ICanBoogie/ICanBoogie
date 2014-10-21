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
 * Instantiates a {@link Core} instance with the auto-config and boots it.
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
 * @param int $length=64
 * @param string $possible=TOKEN_WIDE
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
	return Helpers::pbkdf2($p, $s, $c=1000, $kl=32, $a='sha256');
}
