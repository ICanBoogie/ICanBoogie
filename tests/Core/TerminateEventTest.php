<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Core;

use ICanBoogie\Core;

class TerminateEventTest extends \PHPUnit_Framework_TestCase
{
	public function test_instance()
	{
		$app = $this
			->getMockBuilder('ICanBoogie\Core')
			->disableOriginalConstructor()
			->getMock();

		$request = $this
			->getMockBuilder('ICanBoogie\HTTP\Request')
			->disableOriginalConstructor()
			->getMock();

		$response = $this
			->getMockBuilder('ICanBoogie\HTTP\Response')
			->disableOriginalConstructor()
			->getMock();

		/* @var $app Core */
		/* @var $request \ICanBoogie\HTTP\Request */
		/* @var $response \ICanBoogie\HTTP\Response */

		$called = false;

		\ICanBoogie\app()->events->once(function(Core\TerminateEvent $event, Core $target) use ($app, $request, $response, &$called) {

			$this->assertSame($app, $target);
			$this->assertSame($request, $event->request);
			$this->assertSame($response, $event->response);
			$event->stop();
			$called = true;

		});

		new TerminateEvent($app, $request, $response);

		$this->assertTrue($called);
	}
}
