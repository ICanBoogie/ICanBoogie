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
