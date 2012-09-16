<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Tests\Core\Route;

use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Route;
use ICanBoogie\Core;

class RouteTest extends \PHPUnit_Framework_TestCase
{
	public function testRouteMatchingAndCapture()
	{
		$pattern = '/news/:year-:month-:slug.:format';

		$rc = Route::match('/news/2012-06-this-is-an-example.html', $pattern, $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html'), $captured);

		$rc = Route::match('/news/2012-this-is-an-example.html', $pattern, $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 'this', 'slug' => 'is-an-example', 'format' => 'html'), $captured);

		# using regex

		$pattern = '/news/<year:\d{4}>-<month:\d{2}>-:slug.:format';

		$rc = Route::match('/news/2012-06-this-is-an-example.html', $pattern, $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array('year' => 2012, 'month' => 06, 'slug' => 'this-is-an-example', 'format' => 'html'), $captured);

		#
		# matching should fail because "this" does not match \d{2}
		#

		$rc = Route::match('/news/2012-this-is-an-example.html', $pattern, $captured);

		$this->assertFalse($rc);

		#
		# indexed
		#

		$pattern = '/news/<\d{4}>-<\d{2}>-<[a-z\-]+>.<[a-z]+>';

		$rc = Route::match('/news/2012-06-this-is-an-example.html', $pattern, $captured);

		$this->assertTrue($rc);
		$this->assertEquals(array(2012, 06, 'this-is-an-example', 'html'), $captured);
	}

	public function testRouteCallbackResponse()
	{
		$route = new Route
		(
			'/', array
			(
				'callback' => function(Request $request, Response $response, Route $route)
				{
					return 'madonna';
				}
			)
		);

		$response = $route(Request::from(array('uri' => '/')));

		$this->assertInstanceOf('ICanBoogie\HTTP\Response', $response);
		$this->assertEquals('madonna', $response->body);
	}
}