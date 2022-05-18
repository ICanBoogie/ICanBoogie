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
use ICanBoogie\HTTP\Response;
use PHPUnit\Framework\TestCase;
use Throwable;

use function ICanBoogie\app;
use function ICanBoogie\emit;

final class TerminateEventTest extends TestCase
{
    /**
     * @throws Throwable
     */
    public function test_instance(): void
    {
        $app = $this
            ->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = Request::from([]);
        $response = new Response();

        /* @var $app Application */

        $called = false;

        app()->events->once(function (TerminateEvent $event) use ($app, $request, $response, &$called) {

            $this->assertSame($app, $event->app);
            $this->assertSame($request, $event->request);
            $this->assertSame($response, $event->response);
            $event->stop();
            $called = true;
        });

        emit(new TerminateEvent($app, $request, $response));

        $this->assertTrue($called);
    }
}
