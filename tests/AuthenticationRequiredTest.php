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

class AuthenticationRequiredTest extends \PHPUnit_Framework_TestCase
{
	public function test_message()
	{
		$exception = new AuthenticationRequired;

		$this->assertEquals("The requested URL requires authentication.", $exception->getMessage());
		$this->assertEquals(401, $exception->getCode());
	}
}
