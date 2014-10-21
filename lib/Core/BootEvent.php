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

/**
 * Event class for the `ICanBoogie\Core::boot` event.
 *
 * The event is fired after the core has booted.
 */
class BootEvent extends \ICanBoogie\Event
{
	/**
	 * The event is constructed with the type `boot`.
	 *
	 * @param \ICanBoogie\Core $target
	 */
	public function __construct(\ICanBoogie\Core $target)
	{
		parent::__construct($target, 'boot');
	}
}
