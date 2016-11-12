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
use ICanBoogie\HTTP\Response;

/**
 * Event class for the `ICanBoogie\Application::terminate` event
 *
 * The event is fired after the response to the initial request was sent and that the application
 * is ready to be terminated.
 *
 * @codeCoverageIgnore
 */
class TerminateEvent extends Event
{
	const TYPE = 'terminate';

	/**
	 * @var Request
	 */
	public $request;

	/**
	 * @var Response
	 */
	public $response;

	/**
	 * The event is constructed with the type {@link TYPE}.
	 *
	 * @param Application $target
	 * @param Request $request
	 * @param Response $response
	 */
	public function __construct(Application $target, Request $request, Response $response)
	{
		$this->request = $request;
		$this->response = $response;

		parent::__construct($target, self::TYPE);
	}
}
