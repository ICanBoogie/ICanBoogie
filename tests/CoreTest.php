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
	static private $app;

	static public function setupBeforeClass()
	{
		self::$app = app();
	}

    /**
     * @expectedException \ICanBoogie\CoreAlreadyInstantiated
     */
    public function test_subsequent_construct_should_throw_exception()
    {
        new Core;
    }

    public function test_object_should_have_app_property()
    {
        $o = new Object;
        $this->assertSame(self::$app, $o->app);
    }

	/**
	 * @expectedException \ICanBoogie\CoreAlreadyBooted
	 */
	public function test_second_boot()
	{
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
		$this->assertInternalType('array', $config);
		$this->assertNotEmpty($config);
		$this->assertArrayHasKey('exception_handler', $config);

        unset($app->config);
        $this->assertSame($config, $app->config);
	}

	/**
	 * @dataProvider provide_test_property_type
	 *
	 * @param string $property Property of the Core object.
	 * @param string $class The expected class of the property.
	 */
	public function test_property_type($property, $class)
	{
		$this->assertInstanceOf($class, self::$app->$property);
	}

	public function provide_test_property_type()
	{
		return [

			[ 'vars',              'ICanBoogie\Storage\FileStorage' ],
			[ 'configs',           'ICanBoogie\Config' ],
			[ 'dispatcher',        'ICanBoogie\HTTP\Dispatcher' ],
			[ 'initial_request',   'ICanBoogie\HTTP\Request' ],
			[ 'request',           'ICanBoogie\HTTP\Request' ],
			[ 'events',            'ICanBoogie\EventCollection' ],
			[ 'timezone',          'ICanBoogie\TimeZone' ]

		];
	}

	public function test_set_timezone()
	{
		self::$app->timezone = 3600;
		$this->assertInstanceOf('ICanBoogie\TimeZone', self::$app->timezone);
		$this->assertEquals('Europe/Paris', (string) self::$app->timezone);

		self::$app->timezone = 'Europe/Madrid';
		$this->assertInstanceOf('ICanBoogie\TimeZone', self::$app->timezone);
		$this->assertEquals('Europe/Madrid', (string) self::$app->timezone);
	}

    public function test_invoke()
    {
        $result = uniqid();

        $response = $this
            ->getMockBuilder('ICanBoogie\HTTP\Response')
            ->disableOriginalConstructor()
            ->setMethods([ '__invoke' ])
            ->getMock();
        $response
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($result);

        $request = $this
            ->getMockBuilder('ICanBoogie\HTTP\Request')
            ->disableOriginalConstructor()
            ->setMethods([ '__invoke' ])
            ->getMock();
        $request
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn($response);

        $app = $this
            ->getMockBuilder('ICanBoogie\Core')
            ->disableOriginalConstructor()
            ->setMethods([ 'get_is_booted', 'boot', 'get_initial_request', 'run', 'terminate' ])
            ->getMock();
        $app
            ->expects($this->once())
            ->method('get_is_booted')
            ->willReturn(false);
        $app
            ->expects($this->once())
            ->method('boot');
        $app
            ->expects($this->once())
            ->method('get_initial_request')
            ->willReturn($request);
        $app
            ->expects($this->once())
            ->method('run')
            ->with($request);
        $app
            ->expects($this->once())
            ->method('terminate')
            ->with($request, $response);

        /* @var $app Core */

        $app();
    }
}
