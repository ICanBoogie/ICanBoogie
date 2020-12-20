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
 * Exception thrown in attempt to create a second instance.
 *
 * @codeCoverageIgnore
 */
final class ApplicationAlreadyInstantiated extends LogicException
{
	public const DEFAULT_MESSAGE = "The application is already instantiated.";

	public function __construct(string $message = self::DEFAULT_MESSAGE, Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
	}
}
