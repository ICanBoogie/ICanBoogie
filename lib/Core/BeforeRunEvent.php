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
 * Event class for the `ICanBoogie\Core::run:before` event.
 */
class BeforeRunEvent extends Event
{
	/**
	 * The event is constructed with the type `run:before`.
	 *
	 * @param Core $target
	 */
	public function __construct(Core $target)
	{
		parent::__construct($target, 'run:before');
	}
}
