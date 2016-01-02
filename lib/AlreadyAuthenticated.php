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

use ICanBoogie\HTTP\ClientError;
use ICanBoogie\HTTP\SecurityError;

/**
 * Exception thrown when the user is already authenticated.
 *
 * Third parties may use this exception to redirect authenticated user from a login page to their
 * profile page.
 */
class AlreadyAuthenticated extends ClientError implements SecurityError
{
	const DEFAULT_MESSAGE = "The user is already authenticated.";

	/**
	 * @inheritdoc
	 */
	public function __construct($message = self::DEFAULT_MESSAGE, $code = 401, \Exception $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
