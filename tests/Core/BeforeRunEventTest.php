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

class BeforeRunEventTest extends \PHPUnit_Framework_TestCase
{
	public function test_instance()
	{
		$app = $this
			->getMockBuilder('ICanBoogie\Core')
			->disableOriginalConstructor()
			->getMock();

		/* @var $app Core */

		$called = false;

		\ICanBoogie\app()->events->once(function(Core\BeforeRunEvent $event, Core $target) use ($app, &$called) {

			$this->assertSame($app, $target);
			$event->stop();
			$called = true;

		});

		new BeforeRunEvent($app);

		$this->assertTrue($called);
	}
}
