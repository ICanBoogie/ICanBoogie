<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Object;

use ICanBoogie\Object\PropertyEventTest\A;

class PropertyEventTest extends \PHPUnit_Framework_TestCase
{
	public function test_value()
	{
		$o = new A;
		$success = false;
		$event = new PropertyEvent($o, 'one', $success);
		$this->assertEquals('one', $event->property);
		$this->assertFalse($event->has_value);

		$event->value = null;
		$this->assertTrue($event->has_value);
		$this->assertNull($event->value);

		$event->value = false;
		$this->assertTrue($event->has_value);
		$this->assertFalse($event->value);

		$event->value = true;
		$this->assertTrue($event->has_value);
		$this->assertTrue($event->value);

		$event->value = '123';
		$this->assertTrue($event->has_value);
		$this->assertEquals('123', $event->value);
	}

	/**
	 * @expectedException \ICanBoogie\PropertyNotWritable
	 */
	public function test_set_property()
	{
		$o = new A;
		$success = false;
		$event = new PropertyEvent($o, 'one', $success);
		$event->property = null;
	}

	/**
	 * @expectedException \ICanBoogie\PropertyNotWritable
	 */
	public function test_set_has_value()
	{
		$o = new A;
		$success = false;
		$event = new PropertyEvent($o, 'one', $success);
		$event->has_value = null;
	}
}

namespace ICanBoogie\Object\PropertyEventTest;

class A
{

}
