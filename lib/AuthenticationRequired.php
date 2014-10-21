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
 * Exception thrown when user authentication is required.
 */
class AuthenticationRequired extends SecurityException
{
	public function __construct($message="The requested URL requires authentication.", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}
