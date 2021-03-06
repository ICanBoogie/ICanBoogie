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

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\RequestDispatcher;
use ICanBoogie\Storage\Storage;

class ApplicationAbstractTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @var Application
	 */
	static private $app;

	public static function setUpBeforeClass(): void
	{
		self::$app = app();
	}

    public function test_subsequent_construct_should_throw_exception()
    {
	    $this->expectException(ApplicationAlreadyInstantiated::class);

        new Application;
    }

    public function test_object_should_have_app_property()
    {
        /* @var $o Prototyped|Binding\PrototypedBindings */
        $o = new Prototyped;
        $this->assertSame(self::$app, $o->app);
    }

	public function test_second_boot()
	{
		$this->expectException(ApplicationAlreadyBooted::class);

		self::$app->boot();
	}

    public function test_is_configured()
    {
        $this->assertTrue(self::$app->is_configured);
    }

    public function test_is_booting()
	{
		$this->assertFalse(self::$app->is_booting);
	}

	public function test_is_booted()
	{
		$this->assertTrue(self::$app->is_booted);
	}

    public function test_is_running()
    {
        $this->assertFalse(self::$app->is_running);
    }

	public function test_get_config()
	{
        $app = self::$app;
		$config = $app->config;
		$this->assertIsArray($config);
		$this->assertNotEmpty($config);
		$this->assertArrayHasKey('exception_handler', $config);

        unset($app->config);
        $this->assertSame($config, $app->config);
	}

	/**
	 * @dataProvider provide_test_property_type
	 *
	 * @param string $property Property of the application.
	 * @param string $class The expected class of the property.
	 */
	public function test_property_type($property, $class)
	{
		$this->assertInstanceOf($class, self::$app->$property);
	}

	public function provide_test_property_type()
	{
		return [

			[ 'vars',              Storage::class ],
			[ 'configs',           Config::class ],
			[ 'dispatcher',        RequestDispatcher::class ],
			[ 'initial_request',   Request::class ],
			[ 'request',           Request::class ],
			[ 'events',            EventCollection::class ],
			[ 'timezone',          TimeZone::class ]

		];
	}

	public function test_set_timezone()
	{
		self::$app->timezone = 3600;
		$this->assertInstanceOf(TimeZone::class, self::$app->timezone);
		$this->assertEquals('Europe/Paris', (string) self::$app->timezone);

		self::$app->timezone = 'Europe/Madrid';
		$this->assertInstanceOf(TimeZone::class, self::$app->timezone);
		$this->assertEquals('Europe/Madrid', (string) self::$app->timezone);
	}

    public function test_clear_cache()
    {
    	$invoked = false;

    	$app = app();
    	$app->events->once(function (Application\ClearCacheEvent $event, Application $target) use (&$invoked) {
		    $invoked = true;
	    });

    	$app->clear_cache();

    	$this->assertTrue($invoked);
    }
}
