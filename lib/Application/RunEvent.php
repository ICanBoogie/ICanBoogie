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
final class RunEvent extends Event
{
	public const TYPE = 'run';

	/**
	 * Initial request.
	 *
	 * @var Request
	 */
	private $request;

	protected function get_request(): Request
	{
		return $this->request;
	}

	/**
	 * The event is constructed with the type {@link TYPE}.
	 */
	public function __construct(Application $target, Request $request)
	{
		$this->request = $request;

		parent::__construct($target, self::TYPE);
	}
}
