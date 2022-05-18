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
use PHPUnit\Framework\TestCase;

use function ICanBoogie\app;
use function ICanBoogie\emit;

class BootEventTest extends TestCase
{
    public function test_instance()
    {
        $app = $this
            ->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();

        /* @var $app Application */

        $called = false;

        app()->events->once(function (BootEvent $event) use ($app, &$called) {
            $this->assertSame($app, $event->app);
            $event->stop();
            $called = true;
        });

        emit(new BootEvent($app));

        $this->assertTrue($called);
    }
}
