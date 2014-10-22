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

class CoreNotInstantiated extends \LogicException
{
	public function __construct($message="The core has not been instantiated yet", $code=500, \Exception $previous=null)
	{
		parent::__construct($message, $code, $previous);
	}
}