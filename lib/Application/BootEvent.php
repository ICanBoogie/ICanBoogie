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
