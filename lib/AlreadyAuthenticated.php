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
 * Exception thrown when the user is already authenticated.
 *
 * Third parties may use this exception to redirect authenticated user from a login page to their
 * profile page.
 */
class AlreadyAuthenticated extends SecurityException
{
	public function __construct($message="The user is already authenticated", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}
