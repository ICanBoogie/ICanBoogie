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