<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Routing;

use ICanBoogie\HTTP\Request;
use PHPUnit\Framework\TestCase;

class PingControllerTest extends TestCase
{
    public function test_process()
    {
        $this->markTestSkipped("Routing is broken");

        $controller = new PingController();
        $response = $controller(Request::from('/api/ping'));
        $this->assertEquals("pong", $response->body);
        $response = $controller(Request::from('/api/ping?timer'));
        $this->assertStringStartsWith("pong, in", $response->body);
    }
}
