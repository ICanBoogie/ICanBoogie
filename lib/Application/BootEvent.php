<?php

namespace ICanBoogie\Application;

use ICanBoogie\Application;
use ICanBoogie\Event;

/**
 * The event is emitted after the application has booted.
 *
 * @codeCoverageIgnore
 */
final class BootEvent extends Event
{
    public function __construct(
        public readonly Application $app
    ) {
        parent::__construct();
    }
}
