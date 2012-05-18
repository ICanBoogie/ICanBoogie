<?php

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

class Fixture extends Object
{
	public $a;
	public $b;

	public function __construct()
	{
		unset($this->a);
		unset($this->b);
	}

	protected function get_a()
	{
		return 'a';
	}

	protected function volatile_get_b()
	{
		return 'b';
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
}

class ObjectTest extends \PHPUnit_Framework_TestCase
{
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

	/**
     * @expectedException ICanBoogie\Exception\PropertyNotFound
     */
	public function testGetUndefinedProperty()
	{
		$fixture = new Fixture();
		$madonna = $fixture->madonna;
	}

	public function testReadingReadOnlyProperty()
	{
		$fixture = new Fixture();

		$this->assertEquals('readonly', $fixture->readonly);
	}

	/**
     * @expectedException ICanBoogie\Exception\PropertyNotWritable
     */
	public function testWritingReadOnlyProperty()
	{
		$fixture = new Fixture();
		$fixture->readonly = 'readandwrite';
	}

	/**
     * @expectedException ICanBoogie\Exception\PropertyNotReadable
     */
	public function testReadingWriteOnlyProperty()
	{
		$fixture = new Fixture();

		$fixture->writeonly = 'writeonly';

		$this->assertEquals('writeonly', $fixture->writeonly);
	}

	public function testWritingWriteOnlyProperty()
	{
		$fixture = new Fixture();

		$fixture->writeonly = 'writeonly';

		$this->assertEquals('writeonly', $fixture->read_writeonly);
	}

	/**
	 * Properties with getters should be removed before serialization.
	 */
	public function testSleepAndGetters()
	{
		$fixture = new Fixture();

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

		$fixture = unserialize(serialize(new Fixture()));
		$vars = get_object_vars($fixture);

		$this->assertArrayNotHasKey('a', $vars);
		$this->assertArrayNotHasKey('b', $vars);

		$this->assertEquals('a', $fixture->a);
		$this->assertEquals('b', $fixture->b);
	}
}