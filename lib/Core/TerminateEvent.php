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
use ICanBoogie\HTTP\Response;

/**
 * Event class for the `ICanBoogie\Core::terminate` event
 *
 * The event is fired after the response to the initial request was sent and that the core
 * is ready to be terminated.
 */
class TerminateEvent extends \ICanBoogie\Event
{
	public $request;

	public $response;

	public function __construct(\ICanBoogie\Core $target, Request $request, Response $response)
	{
		$this->request = $request;
		$this->response = $response;

		parent::__construct($target, 'terminate');
	}
}
