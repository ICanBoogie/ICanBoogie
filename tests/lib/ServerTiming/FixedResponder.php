<?php

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;

use function getenv;

final class FixedResponder implements Responder
{
    public static Response $response;

    public function respond(Request $request): Response
    {
        $response = self::$response = new Response('body');

        if (getenv('SERVER_TIMING_SCENARIO') === 'preexisting') {
            $response->headers->server_timing->add('responder', 12.5);
        }

        return $response;
    }
}
