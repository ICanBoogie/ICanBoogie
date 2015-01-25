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

use ICanBoogie\HTTP\Request;

/**
 * Event class for the `ICanBoogie\Core::run` event.
 */
class RunEvent extends \ICanBoogie\Event
{
	/**
	 * Initial request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * The event is constructed with the type `run`.
	 *
	 * @param \ICanBoogie\Core $target
	 * @param Request $request
	 */
	public function __construct(\ICanBoogie\Core $target, Request $request)
	{
		$this->request = $request;

		parent::__construct($target, 'run');
	}
}
