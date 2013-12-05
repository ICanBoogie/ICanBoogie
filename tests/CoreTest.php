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

class CoreTest extends \PHPUnit_Framework_TestCase
{
	static private $core;

	static public function setupBeforeClass()
	{
		self::$core = new Core(array(

			'connections' => array
			(
				'primary' => array
				(
					'dsn' => 'sqlite::memory:'
				)
			)

		));
	}

	/**
	 * @dataProvider provide_test_write_readonly_properties
	 * @expectedException ICanBoogie\PropertyNotWritable
	 *
	 * @param string $property Property name.
	 */
	public function test_write_readonly_properties($property)
	{
		self::$core->$property = null;
	}

	public function provide_test_write_readonly_properties()
	{
		$properties = 'dispatcher|language|request|routes';

		return array_map(function($v) { return (array) $v; }, explode('|', $properties));
	}

	/**
	 * @dataProvider provide_test_property_type
	 *
	 * @param string $property Property of the Core object.
	 * @param string $class The expected class of the property.
	 */
	public function test_property_type($property, $class)
	{
		$this->assertInstanceOf($class, self::$core->$property);
	}

	public function provide_test_property_type()
	{
		return array
		(
			array('modules',         'ICanBoogie\Modules'),
			array('models',          'ICanBoogie\ActiveRecord\Models'),
			array('vars',            'ICanBoogie\Vars'),
			array('connections',     'ICanBoogie\ActiveRecord\Connections'),
			array('db',              'ICanBoogie\ActiveRecord\Connection'),
			array('configs',         'ICanBoogie\Configs'),
			array('dispatcher',      'ICanBoogie\HTTP\Dispatcher'),
			array('initial_request', 'ICanBoogie\HTTP\Request'),
			array('request',         'ICanBoogie\HTTP\Request'),
			array('locale',          'ICanBoogie\I18n\Locale'),
			array('events',          'ICanBoogie\Events'),
			array('routes',          'ICanBoogie\Routes')
		);
	}
}
