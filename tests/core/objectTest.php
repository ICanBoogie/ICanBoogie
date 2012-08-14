<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Tests\Core\Object;

use ICanBoogie\Exception\PropertyNotReadable;
use ICanBoogie\Exception\PropertyNotWritable;
use ICanBoogie\Object;

class TimeFixture extends Object
{
	public $seconds;

	protected function volatile_set_minutes($minutes)
	{
		$this->seconds = $minutes * 60;
	}

	protected function volatile_get_minutes()
	{
		return $this->seconds / 60;
	}
}

class A extends Object
{
	public $a;
	public $b;
	public $unset;
	protected $unset_protected;

	public function __construct()
	{
		unset($this->a);
		unset($this->b);
		unset($this->unset);
		unset($this->unset_protected);
	}

	protected function get_a()
	{
		return 'a';
	}

	protected function volatile_get_b()
	{
		return 'b';
	}

	protected $c;

	protected function set_c($value)
	{
		return $value;
	}

	protected function get_c()
	{
		return $this->c;
	}

	protected $d;

	protected function volatile_set_d($value)
	{
		$this->d = $value;
	}

	protected function volatile_get_d()
	{
		return $this->d;
	}

	private $e;

	protected function set_e($value)
	{
		return $value;
	}

	protected function get_e()
	{
		return $this->e;
	}

	protected $f;

	protected function volatile_set_f($value)
	{
		$this->f = $value;
	}

	protected function volatile_get_f()
	{
		return $this->f;
	}

	private $readonly = 'readonly';

	protected function volatile_get_readonly()
	{
		return $this->readonly;
	}

	private $writeonly;

	protected function volatile_set_writeonly($value)
	{
		$this->writeonly = $value;
	}

	protected function volatile_get_read_writeonly()
	{
		return $this->writeonly;
	}

	protected function get_pseudo_uniq()
	{
		return uniqid();
	}

	protected function set_with_parent($value)
	{
		return $value + 1;
	}
}

class B extends A
{
	protected function set_with_parent($value)
	{
		return parent::set_with_parent($value) * 10;
	}
}

class ObjectTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException ICanBoogie\Exception\PropertyNotFound
	 */
	public function testGetUnsetPublicProperty()
	{
		$fixture = new A();
		$fixture->unset;
	}

	/**
	 * @expectedException ICanBoogie\Exception\PropertyNotReadable
	 */
	public function testGetUnsetProtectedProperty()
	{
		$fixture = new A();
		$fixture->unset_protected;
	}

	/**
	 * @expectedException ICanBoogie\Exception\PropertyNotFound
	 */
	public function testGetUndefinedProperty()
	{
		$fixture = new A();
		$fixture->madonna;
	}

	public function testProtectedProperty()
	{
		$fixture = new A();
		$fixture->c = 'c';

		$this->assertEquals('c', $fixture->c);
	}

	public function testProtectedVolatileProperty()
	{
		$fixture = new A();
		$fixture->d = 'd';

		$this->assertEquals('d', $fixture->d);
	}

	/**
	 * Because `e` is private it is not accessible by the Object class that tries to set
	 * the result of the `set_e` setter, but PHP won't complain about it and will simply leave
	 * the property untouched. This situation is not ideal because an error would be nice, so we
	 * have to note that setters for private properties MUST be _volatile_, that their job is
	 * to set the property and that we encourage using protected properties.
	 */
	public function testPrivateProperty()
	{
		$fixture = new A();
		$fixture->e = 'e';

		$this->assertEquals(null, $fixture->e);
	}

	public function testPrivateVolatileProperty()
	{
		$fixture = new A();
		$fixture->f = 'f';

		$this->assertEquals('f', $fixture->f);
	}

	/**
	 * The `minute` property is virtual and works with seconds.
	 */
	public function testVolatile()
	{
		$time = new TimeFixture();

		$time->minutes = 1;
		$this->assertEquals(1, $time->minutes);
		$this->assertEquals(60, $time->seconds);

		$time->seconds = 120;
		$this->assertEquals(2, $time->minutes);

		$time->minutes *= 2;
		$this->assertEquals(240, $time->seconds);
		$this->assertEquals(4, $time->minutes);
	}

	public function testReadingReadOnlyProperty()
	{
		$fixture = new A();

		$this->assertEquals('readonly', $fixture->readonly);
	}

	/**
	 * @expectedException ICanBoogie\Exception\PropertyNotWritable
	 */
	public function testWritingReadOnlyProperty()
	{
		$fixture = new A();
		$fixture->readonly = 'readandwrite';
	}

	/**
	 * @expectedException ICanBoogie\Exception\PropertyNotReadable
	 */
	public function testReadingWriteOnlyProperty()
	{
		$fixture = new A();

		$fixture->writeonly = 'writeonly';

		$this->assertEquals('writeonly', $fixture->writeonly);
	}

	public function testWritingWriteOnlyProperty()
	{
		$fixture = new A();

		$fixture->writeonly = 'writeonly';

		$this->assertEquals('writeonly', $fixture->read_writeonly);
	}

	/**
	 * Properties with getters should be removed before serialization.
	 */
	public function testSleepAndGetters()
	{
		$fixture = new A();

		$this->assertEquals('a', $fixture->a);
		$this->assertEquals('b', $fixture->b);

		$fixture = $fixture->__sleep();

		$this->assertArrayNotHasKey('a', $fixture);
		$this->assertArrayNotHasKey('b', $fixture);
	}

	/**
	 * Null properties with getters should be unset when the object wakeup so that getters can
	 * be called when the properties are accessed.
	 */
	public function testAwakeAndGetters()
	{
		#
		# we use get_object_vars() otherwise assertion method would call getters
		#

		$fixture = unserialize(serialize(new A()));
		$vars = get_object_vars($fixture);

		$this->assertArrayNotHasKey('a', $vars);
		$this->assertArrayNotHasKey('b', $vars);

		$this->assertEquals('a', $fixture->a);
		$this->assertEquals('b', $fixture->b);
	}

	/**
	 * The `pseudo_uniq` property use a lazyloading getter, that is the property is created as
	 * public after the getter has been called, and the getter won't be called again until the
	 * property is accessible.
	 */
	public function testPseudoUnique()
	{
		$fixture = new A();
		$uniq = $fixture->pseudo_uniq;

		$this->assertNotEmpty($uniq);
		$this->assertEquals($uniq, $fixture->pseudo_uniq);
		unset($fixture->pseudo_uniq);
		$this->assertNotEquals($uniq, $fixture->pseudo_uniq);
	}

	public function testSetWithParent()
	{
		$b = new B();
		$b->with_parent = 3;
		$this->assertEquals(40, $b->with_parent);
	}
}