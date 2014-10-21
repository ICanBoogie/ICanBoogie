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
 * @return Core|null The {@link Core} instance, or `null` if it hasn't been instantiated yet.
 */
function app()
{
	return Core::get();
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
