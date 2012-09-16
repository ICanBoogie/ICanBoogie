<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\HTTP;

use ICanBoogie\Operation;
use ICanBoogie\Routes;

/**
 * Dispatches requests.
 *
 * The request is dispatched using controllers which can be defined during the
 * {@link Dispatcher\PopulateEvent}. By default dispatchers are setup for operations and routes.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::populate
 * -------------------------------------------
 *
 * Third parties may use the {@link Dispatcher\PopulateEvent} to populate the controllers
 * that will be used to dispatch requests. The event is fired during {@link __construct()}.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::dispatch:before
 * --------------------------------------------------
 *
 * Third parties may use the {@link Dispatcher\BeforeDispatchEventto provide a response
 * before the controllers are invoked. The event is fired during {@link __invoke()}.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::dispatch
 * -------------------------------------------
 *
 * Third parties may use the {@link Dispatcher\DispatchEvent} to alter the response returned by
 * dispatchers. The event is fired during {@link __invoke()}.
 */
class Dispatcher
{
	static public function dispatch_operation(Request $request)
	{
		$operation = Operation::from($request);

		if (!$operation)
		{
			return;
		}

		$response = $operation($request);

		#
		# If the response is an error and the request is not XHR we allow the
		# dispatch to continue, one hook might display an error message.
		#

		$is_api_operation = strpos($request->path, '/api/') === 0;

		if ($response && ($response->is_client_error || $response->is_server_error) && !$request->is_xhr)
		{
			return $is_api_operation ? $response : null;
		}

		if (!$response && $is_api_operation)
		{
			$response = new Response(404);
		}

		return $response;
	}

	protected $controllers = array
	(
		'operation' => array(__CLASS__, 'dispatch_operation'),
		'route' => 'ICanBoogie\Routes::dispatch_request'
	);

	/**
	 * Fires the {@link Dispatcher\PopulateEvent}.
	 */
	public function __construct()
	{
		new Dispatcher\PopulateEvent($this, array('controllers' => &$this->controllers));
	}

	/**
	 * The request is dispatched using the event system and the operation system. The goal is to
	 * retrieve a {@link Response}:
	 *
	 * 1. The {@link Dispatcher\BeforeDispatchEvent} is fired.
	 * 2. The controllers chain is traversed until a controller returns a response object.
	 * 3. The {@link Dispatcher\DispatchEvent} is fired.
	 *
	 * @param Request $request
	 *
	 * @throws \ICanBoogie\Exception\HTTP with code 404 if no response could be obtained from the
	 * events or the controllers.
	 *
	 * @return Response
	 */
	public function __invoke(Request $request)
	{
		$response = null;

		new Dispatcher\BeforeDispatchEvent($this, array('request' => $request, 'response' => &$response));

		if (!$response)
		{
			foreach ($this->controllers as $handler)
			{
				$response = call_user_func($handler, $request);

				if ($response) break;
			}
		}

		new Dispatcher\DispatchEvent($this, array('request' => $request, 'response' => &$response));

		if (!$response)
		{
			throw new \ICanBoogie\Exception\HTTP('The requested URL was not found on this server.', array(), 404);
		}

		return $response;
	}

	protected function route($method, $path, $callback, array $options=array())
	{
		Routes::add
		(
			$method . ' ' . $path, array
			(
				'pattern' => $path,
				'via' => $method,
				'callback' => $callback
			)

			+ $options
		);
	}

	public function any($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_ANY, $path, $callback, $options);
	}

	public function get($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_GET, $path, $callback, $options);
		$this->route(Request::METHOD_HEAD, $path, $callback, $options);
	}

	public function post($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_POST, $path, $callback, $options);
	}

	public function put($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_PUT, $path, $callback, $options);
	}

	public function delete($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_DELETE, $path, $callback, $options);
	}

	public function head($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_HEAD, $path, $callback, $options);
	}

	public function options($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_OPTIONS, $path, $callback, $options);
	}

	public function patch($path, $callback, array $options=array())
	{
		$this->route(Request::METHOD_PATCH, $path, $callback, $options);
	}
}

namespace ICanBoogie\HTTP\Dispatcher;

/**
 * Event class for the `ICanBoogie\HTTP\Dispatcher::populate` event.
 *
 * Third parties may use this event to register additionnal controllers.
 */
class PopulateEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the controllers array.
	 *
	 * @var array[string]callable
	 */
	public $controllers;

	/**
	 * The event is constructed with the type `populate`.
	 *
	 * @param \ICanBoogie\HTTP\Dispatcher $target
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\HTTP\Dispatcher $target, array $properties)
	{
		parent::__construct($target, 'populate', $properties);
	}
}

/**
 * Event class for the `ICanBoogie\HTTP\Dispatcher::dispatch:before` event.
 *
 * Third parties may use this event to provide a response to the request before dispatcher
 * controllers are invoked. The event is usually used to redirect request or to provide cached
 * responses.
 */
class BeforeDispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The HTTP request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * Reference to the HTTP response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch:before`.
	 *
	 * @param \ICanBoogie\HTTP\Dispatcher $target
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\HTTP\Dispatcher $target, array $properties)
	{
		parent::__construct($target, 'dispatch:before', $properties);
	}
}

/**
 * Event class for the `ICanBoogie\HTTP\Dispatcher::dispatch` event.
 *
 * Third parties may use this event to alter the response.
 */
class DispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The HTTP request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * Reference to the HTTP response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param \ICanBoogie\HTTP\Dispatcher $target
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\HTTP\Dispatcher $target, array $properties)
	{
		parent::__construct($target, 'dispatch', $properties);
	}
}