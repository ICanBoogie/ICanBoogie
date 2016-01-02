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
use ICanBoogie\HTTP\Request;

/**
 * Event class for the `ICanBoogie\Core::run` event.
 */
class RunEvent extends Event
{
	const TYPE = 'run';

	/**
	 * Initial request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * The event is constructed with the type {@link TYPE}.
	 *
	 * @param Core $target
	 * @param Request $request
	 */
	public function __construct(Core $target, Request $request)
	{
		$this->request = $request;

		parent::__construct($target, self::TYPE);
	}
}
