<?php

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * The event is emitted after the response to the initial request was sent and that the application is ready to be
 * terminated.
 *
 * @codeCoverageIgnore
 */
final class TerminateEvent extends Event
{
    public function __construct(
        public readonly Application $app,
        public readonly Request $request,
        public readonly Response $response
    ) {
        parent::__construct();
    }
}
