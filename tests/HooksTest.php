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

class HooksTest extends \PHPUnit_Framework_TestCase
{
	public function test_get_routes()
	{
		$app = app();

		$routes = Hooks::lazy_get_routes($app);
		$this->assertInstanceOf('ICanBoogie\Routing\Routes', $routes);
		$this->assertTrue(isset($routes['api:core/ping']));
		$this->assertEquals($routes, $app->routes);
		$this->assertSame($app->routes, $app->routes);
	}
}
