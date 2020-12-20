<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\HTTP\Request;

use function ICanBoogie\app;

class RunEventTest extends \PHPUnit\Framework\TestCase
{
	public function test_instance()
	{
		$app = $this
			->getMockBuilder(Application::class)
			->disableOriginalConstructor()
			->getMock();

		$request = $this
			->getMockBuilder(Request::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var $app Application */
		/* @var $request Request */

		$called = false;

		app()->events->once(function (RunEvent $event, Application $target) use ($app, $request, &$called) {

			$this->assertSame($app, $target);
			$this->assertSame($request, $event->request);
			$event->stop();
			$called = true;

		});

		new RunEvent($app, $request);

		$this->assertTrue($called);
	}
}
