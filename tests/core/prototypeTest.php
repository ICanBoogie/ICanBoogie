<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Tests\Core\Prototype;

use ICanBoogie\Object;

class A extends Object
{

}

class B extends A
{

}

class PrototypeTest extends \PHPUnit_Framework_TestCase
{
	private $a;
	private $b;

	public function setUp()
	{
		$this->a = $a = new A();
		$this->b = $b = new B();

		$a->prototype['volatile_set_minutes'] = function(A $self, $minutes) {

			$self->seconds = $minutes * 60;
		};

		$a->prototype['volatile_get_minutes'] = function(A $self, $minutes) {

			return $self->seconds / 60;
		};
	}

	public function testPrototype()
	{
		$this->assertInstanceOf('ICanBoogie\Prototype', $this->a->prototype);
	}

	public function testMethod()
	{
		$a = $this->a;

		$a->prototype['format'] = function(A $self, $format) {

			return date($format, $self->seconds);
		};

		$a->seconds = time();
		$format = 'H:i:s';

		$this->assertEquals(date($format, $a->seconds), $a->format($format));
	}

	public function testSetterGetter()
	{
		$a = $this->a;

		$a->minutes = 2;

 		$this->assertEquals(120, $a->seconds);
 		$this->assertEquals(2, $a->minutes);
	}

	public function testPrototypeChain()
	{
		$b = $this->b;

		$b->prototype['volatile_set_hours'] = function(B $self, $hours) {

			$self->seconds = $hours * 3600;
		};

		$b->prototype['volatile_get_hours'] = function(B $self, $hours) {

			return $self->seconds / 3600;
		};

		$b->minutes = 4;

		$this->assertEquals(240, $b->seconds);
		$this->assertEquals(4, $b->minutes);

		$b->hours = 1;

		$this->assertEquals(3600, $b->seconds);
		$this->assertEquals(1, $b->hours);

		# hours should be a simple property for A

		$a = $this->a;

		$a->seconds = 0;
		$a->hours = 1;

		$this->assertEquals(0, $a->seconds);
		$this->assertEquals(1, $a->hours);
	}
}