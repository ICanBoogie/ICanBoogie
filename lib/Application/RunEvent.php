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
use ICanBoogie\HTTP\Request;

/**
 * Event class for the `ICanBoogie\Application::run` event.
 *
 * @property-read Request $request
 *
 * @codeCoverageIgnore
 */
class RunEvent extends Event
{
	const TYPE = 'run';

	/**
	 * Initial request.
	 *
	 * @var Request
	 */
	private $request;

	/**
	 * @return Request
	 */
	protected function get_request()
	{
		return $this->request;
	}

	/**
	 * The event is constructed with the type {@link TYPE}.
	 *
	 * @param Application $target
	 * @param Request $request
	 */
	public function __construct(Application $target, Request $request)
	{
		$this->request = $request;

		parent::__construct($target, self::TYPE);
	}
}
