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
 * The event is emitted when caches must be cleared.
 *
 * @codeCoverageIgnore
 */
final class ClearCacheEvent extends Event
{
    public function __construct(Application $sender)
    {
        parent::__construct($sender);
    }
}
