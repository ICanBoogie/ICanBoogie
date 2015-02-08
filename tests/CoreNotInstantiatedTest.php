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

class CoreNotInstantiatedTest extends \PHPUnit_Framework_TestCase
{
	public function test_message()
	{
		$exception = new CoreNotInstantiated;

		$this->assertEquals("The core has not been instantiated yet.", $exception->getMessage());
		$this->assertEquals(500, $exception->getCode());
	}
}
