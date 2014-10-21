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
 * Exception thrown when a user doesn't have the required permission.
 */
class PermissionRequired extends SecurityException
{
	public function __construct($message="You don't have the required permission.", $code=401, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}
