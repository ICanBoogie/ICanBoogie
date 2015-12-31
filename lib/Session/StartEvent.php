<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Session;

use ICanBoogie\Event;
use ICanBoogie\Session;

/**
 * Event class for the `ICanBoogie\Session::start` event.
 */
class StartEvent extends Event
{
	const TYPE = 'start';

	/**
	 * The event is constructed with the type `start`.
	 *
	 * @param Session $target
	 */
	public function __construct(Session $target)
	{
		parent::__construct($target, self::TYPE);
	}
}
