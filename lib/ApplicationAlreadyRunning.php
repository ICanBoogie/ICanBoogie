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

use LogicException;
use Throwable;

/**
 * Exception thrown in attempt to run the application a second time.
 *
 * @codeCoverageIgnore
 */
final class ApplicationAlreadyRunning extends LogicException
{
	public const DEFAULT_MESSAGE = "The application is already running.";

	public function __construct(string $message = self::DEFAULT_MESSAGE, Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
	}
}
