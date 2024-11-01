<?php

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\Event;
use ICanBoogie\HTTP\Request;

/**
 * The event is emitted when the application runs.
 *
 * @codeCoverageIgnore
 */
final class RunEvent extends Event
{
    public function __construct(
        public readonly Application $app,
        public readonly Request $request
    ) {
        parent::__construct();
    }
}
