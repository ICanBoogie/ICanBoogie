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
use ICanBoogie\HTTP\Response;

/**
 * Event class for the `ICanBoogie\Core::terminate` event
 *
 * The event is fired after the response to the initial request was sent and that the core
 * is ready to be terminated.
 */
class TerminateEvent extends Event
{
	/**
	 * @var Request
	 */
	public $request;

	/**
	 * @var Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `terminate`.
	 *
	 * @param Core $target
	 * @param Request $request
	 * @param Response $response
	 */
	public function __construct(Core $target, Request $request, Response $response)
	{
		$this->request = $request;
		$this->response = $response;

		parent::__construct($target, 'terminate');
	}
}
