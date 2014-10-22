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

/**
 * Event class for the `ICanBoogie\Session::start` event.
 */
class StartEvent extends \ICanBoogie\Event
{
	/**
	 * The event is constructed with the type `start`.
	 *
	 * @param \ICanBoogie\Session $target
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Session $target, array $payload=[])
	{
		parent::__construct($target, 'start', $payload);
	}
}
