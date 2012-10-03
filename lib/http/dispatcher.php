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
 * Requests are dispatched using controllers which can be defined during the
 * {@link Dispatcher\PopulateEvent}. By default dispatchers are setup for operations and routes.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::populate
 * -------------------------------------------
 *
 * Third parties may use {@link Dispatcher\PopulateEvent} to populate the controllers
 * that will be used to dispatch requests. The event is fired during {@link __construct()}.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::dispatch:before
 * --------------------------------------------------
 *
 * Third parties may use {@link Dispatcher\BeforeDispatchEventto provide a response
 * before the controllers are invoked. The event is fired during {@link __invoke()}.
 *
 *
 *
 * Event: ICanBoogie\HTTP\Dispatcher::dispatch
 * -------------------------------------------
 *
 * Third parties may use {@link Dispatcher\DispatchEvent} to alter the response returned by
 * dispatchers, or provide a new one. The event is fired during {@link __invoke()}.
 */
class Dispatcher
{
	/**
	 * The controller functions called during the dispatching of the request.
	 *
	 * @var array[string]callable
	 */
	protected $controllers = array
	(
		'operation' => 'ICanBoogie\Operation::dispatch_request',
		'route' => 'ICanBoogie\Routes::dispatch_request'
	);

	/**
	 * Fires {@link Dispatcher\PopulateEvent}.
	 */
	public function __construct()
	{
		new Dispatcher\PopulateEvent($this, array('controllers' => &$this->controllers));
	}

	/**
	 * The request is dispatched using the event system and the operation system. The goal is to
	 * retrieve a {@link Response}:
	 *
	 * 1. {@link Dispatcher\BeforeDispatchEvent} is fired.
	 * 2. The controllers chain is traversed until a controller returns a response object.
	 * 3. {@link Dispatcher\DispatchEvent} is fired.
	 *
	 * @param Request $request
	 *
	 * @return Response
	 *
	 * @throws \ICanBoogie\Exception\HTTP with code 404 if no response could be obtained from the
	 * events or the controllers.
	 */
	public function __invoke(Request $request)
	{
		try
		{
			return $this->dispatch($request);
		}
		catch (\Exception $e)
		{
			return $this->dispatch_exception($e, $request);
		}
	}

	/**
	 * Dispatches a request.
	 *
	 * The following event are fired during the dispatching:
	 *
	 * - {@link Dispatcher\BeforeDispatchEvent} is fired before controllers are traversed. If a
	 * response is provided the controllers are skipped.
	 *
	 * - {@link Dispatcher\DispatchEvent} is fired before the response is returned.
	 *
	 * @param Request $request
	 *
	 * @return Response
	 *
	 * @throws \ICanBoogie\Exception\HTTP when neither the events nor the controllers are able
	 * to provide a response to the request.
	 */
	protected function dispatch(Request $request)
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

	/**
	 * Tries to get a {@link Response} object for an exception.
	 *
	 * {@link \ICanBoogie\Exception\GetResponseEvent} is fired with the exception as target.
	 * The response provided by one of the event callbacks is returned. If there is no response the
	 * exception is thrown again.
	 *
	 * @param \Exception $exception
	 * @param Request $request
	 *
	 * @return Response
	 *
	 * @throws \Exception when there is no response for the exception.
	 */
	protected function dispatch_exception(\Exception $exception, Request $request)
	{
		$response = null;

		new \ICanBoogie\Exception\GetResponseEvent($exception, array('response' => &$response, 'exception' => &$exception, 'request' => $request));

		if (!$response)
		{
			throw $exception;
		}

		return $response;
	}

	/*
	 * TODO-20120906: move all of this to Routes:
	 *
	 * $core->routes->any('/', function(Request $request) { return 'index!'; });
	 *
	 * -> use __call() (get() seams tricky)
	 */
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
 * Third parties may use this event to provide a response to the request before the controllers
 * are invoked. The event is usually used by third parties to redirect requests or provide cached
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
 * Third parties may use this event to alter the response before it is returned by the dispatcher.
 */
class DispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * Reference to the response.
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

namespace ICanBoogie\Exception;

/**
 * Event class for the `Exception\get_response` event type.
 */
class GetResponseEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * Reference tot the exception.
	 *
	 * @var \Exception
	 */
	public $exception;

	/**
	 * The request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * The event is constructed with the type `get_response`.
	 *
	 * @param \Exception $target
	 * @param array $properties
	 */
	public function __construct(\Exception $target, array $properties)
	{
		parent::__construct($target, 'get_response', $properties);
	}
}