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
use ICanBoogie\Event;

/**
 * The event is emitted when the application is configured. Listeners may use this event to further configure the
 * application.
 *
 * @codeCoverageIgnore
 */
final class ConfigureEvent extends Event
{
    public function __construct(
        public readonly Application $app
    ) {
        parent::__construct();
    }
}
