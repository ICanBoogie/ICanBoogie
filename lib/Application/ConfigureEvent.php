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
 * Event class for the `ICanBoogie\Application::configure` event.
 *
 * The event is fired when the application is configured.
 *
 * @codeCoverageIgnore
 */
final class ConfigureEvent extends Event
{
	public const TYPE = 'configure';

	/**
	 * The event is constructed with the type {@link TYPE}.
	 */
	public function __construct(Application $target)
	{
		parent::__construct($target, self::TYPE);
	}
}
