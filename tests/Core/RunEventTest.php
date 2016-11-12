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
use ICanBoogie\HTTP\Request;

class RunEventTest extends \PHPUnit_Framework_TestCase
{
	public function test_instance()
	{
		$app = $this
			->getMockBuilder(Core::class)
			->disableOriginalConstructor()
			->getMock();

		$request = $this
			->getMockBuilder(Request::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var $app Core */
		/* @var $request Request */

		$called = false;

		\ICanBoogie\app()->events->once(function (RunEvent $event, Core $target) use ($app, $request, &$called) {

			$this->assertSame($app, $target);
			$this->assertSame($request, $event->request);
			$event->stop();
			$called = true;

		});

		new RunEvent($app, $request);

		$this->assertTrue($called);
	}
}
