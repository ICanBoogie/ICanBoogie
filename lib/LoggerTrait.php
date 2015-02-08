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
 * Adds the `success()` method to the PSR.
 */
trait LoggerTrait
{
	use \Psr\Log\LoggerTrait;

	/**
	 * A successful event.
	 *
	 * Example: An operation was successfully performed.
	 *
	 * @param string $message
	 * @param array $context
	 * @return null
	 */
	public function success($message, array $context = [])
	{
		$this->log(LogLevel::SUCCESS, $message, $context);
	}
}
