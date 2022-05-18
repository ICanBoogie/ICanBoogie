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
use PHPUnit\Framework\TestCase;

use function ICanBoogie\app;
use function ICanBoogie\emit;

class RunEventTest extends TestCase
{
    public function test_instance(): void
    {
        $app = $this
            ->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = Request::from([]);

        $called = false;

        app()->events->once(function (RunEvent $event) use ($app, $request, &$called) {
            $this->assertSame($app, $event->app);
            $this->assertSame($request, $event->request);
            $event->stop();
            $called = true;
        });

        emit(new RunEvent($app, $request));

        $this->assertTrue($called);
    }
}
