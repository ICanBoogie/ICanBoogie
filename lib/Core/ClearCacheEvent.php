<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Core;

use ICanBoogie\Core;
use ICanBoogie\Event;

/**
 * Representation of the `clear_cache` event.
 *
 * The event is fired when all the cache of the application must be cleared.
 */
class ClearCacheEvent extends Event
{
	const TYPE = 'clear_cache';

	public function __construct(Core $app)
	{
		parent::__construct($app, self::TYPE);
	}
}
