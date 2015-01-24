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
	/**
	 * @var Core
	 */
	static private $core;

	static public function setupBeforeClass()
	{
		self::$core = app();
	}

	/**
	 * @expectedException \ICanBoogie\CoreAlreadyBooted
	 */
	public function test_second_boot()
	{
		self::$core->boot();
	}

	/**
	 * @dataProvider provide_test_write_readonly_properties
	 * @expectedException \ICanBoogie\PropertyNotWritable
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
		return [

			[ 'vars',              'ICanBoogie\Storage\FileStorage' ],
			[ 'configs',           'ICanBoogie\Config' ],
			[ 'dispatcher',        'ICanBoogie\HTTP\Dispatcher' ],
			[ 'initial_request',   'ICanBoogie\HTTP\Request' ],
			[ 'request',           'ICanBoogie\HTTP\Request' ],
			[ 'events',            'ICanBoogie\Events' ],
			[ 'routes',            'ICanBoogie\Routing\Routes' ],
			[ 'timezone',          'ICanBoogie\TimeZone' ],
			[ 'connections',       'ICanBoogie\ActiveRecord\Connections' ],
			[ 'db',                'ICanBoogie\ActiveRecord\Connection' ],
			[ 'template_engines',  'ICanBoogie\Render\EngineCollectionInterface' ],
			[ 'template_resolver', 'ICanBoogie\Render\TemplateResolverInterface' ],
			[ 'template_resolver', 'ICanBoogie\ApplicationTemplateResolver' ]

		];
	}

	public function test_set_timezone()
	{
		self::$core->timezone = 3600;
		$this->assertInstanceOf('ICanBoogie\TimeZone', self::$core->timezone);
		$this->assertEquals('Europe/Paris', (string) self::$core->timezone);

		self::$core->timezone = 'Europe/Madrid';
		$this->assertInstanceOf('ICanBoogie\TimeZone', self::$core->timezone);
		$this->assertEquals('Europe/Madrid', (string) self::$core->timezone);
	}
}
