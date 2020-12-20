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

use function ICanBoogie\app;

class BootEventTest extends \PHPUnit\Framework\TestCase
{
	public function test_instance()
	{
		$app = $this
			->getMockBuilder(Application::class)
			->disableOriginalConstructor()
			->getMock();

		/* @var $app Application */

		$called = false;

		app()->events->once(function (BootEvent $event, Application $target) use ($app, &$called) {

			$this->assertSame($app, $target);
			$event->stop();
			$called = true;

		});

		new BootEvent($app);

		$this->assertTrue($called);
	}
}
