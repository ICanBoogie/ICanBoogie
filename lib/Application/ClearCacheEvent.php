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
 * Event class for the `ICanBoogie\Application::clear_cache` event.
 *
 * The event is fired when all the cache of the application must be cleared.
 *
 * @codeCoverageIgnore
 */
final class ClearCacheEvent extends Event
{
    public const TYPE = 'clear_cache';

    /**
     * The event is constructed with the type {@link TYPE}.
     */
    public function __construct(Application $target)
    {
        parent::__construct($target, self::TYPE);
    }
}
