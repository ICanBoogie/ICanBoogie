<?php

namespace ICanBoogie\Responder;

use ICanBoogie\HTTP\Request;
use PHPUnit\Framework\TestCase;

use function ICanBoogie\app;

class PingResponderTest extends TestCase
{
    public function test_process(): void
    {
        $responder = new PingResponder(app());
        $response = $responder->respond(Request::from('/api/ping'));
        $this->assertEquals("pong", $response->body);
        $response = $responder->respond(Request::from('/api/ping?timer'));
        $body = $response->body;
        $this->assertIsString($body);
        /** @var string $body */
        $this->assertStringStartsWith("pong, in", $body);
    }
}
