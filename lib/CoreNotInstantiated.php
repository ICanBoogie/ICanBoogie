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
 * Exception thrown in attempt to obtain the core before is has been instantiated.
 *
 * @codeCoverageIgnore
 */
class CoreNotInstantiated extends \LogicException
{
	const DEFAULT_MESSAGE = "The core has not been instantiated yet.";

	/**
	 * @inheritdoc
	 */
	public function __construct($message = self::DEFAULT_MESSAGE, $code = 500, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
