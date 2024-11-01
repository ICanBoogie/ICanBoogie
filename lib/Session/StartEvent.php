<?php

namespace ICanBoogie\Session;

use ICanBoogie\Event;
use ICanBoogie\Session;

/**
 * The event is emitted when the session starts.
 *
 * @codeCoverageIgnore
 */
final class StartEvent extends Event
{
    public function __construct(Session $sender)
    {
        parent::__construct($sender);
    }
}
