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

class Dispatcher
{
	public static function dispatch_operation(Request $request)
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

	public static function dispatch_route(Request $request)
	{
		$path = $request->path;
		$path = preg_replace('/^\/index\.(html|php)/', '/', $path);

		$route = \ICanBoogie\Routes::get()->find($path, $request->method);

		if ($route)
		{
			$response = $route($request);
		}
		else
		{
			$response = null;
		}

		return $response;
	}

	protected $controllers = array
	(
		'operation' => array(__CLASS__, 'dispatch_operation'),
		'route' => array(__CLASS__, 'dispatch_route')
	);

	public function __construct()
	{
		new Dispatcher\PopulateEvent($this, array('controllers' => &$this->controllers));
	}

	/**
	 *
	 * The request is dispatched using the event system and the operation system. The goal is to
	 * retrieve a {@link Response}:
	 *
	 * - The `ICanBoogie\HTTP\Request::dispatch:before` event of class
	 * `ICanBoogie\HTTP\Request\BeforeDispatchEvent` class is fired with a reference to an
	 * `null` response variable. Event hooks might use this event to provide the response.
	 *
	 * - If an operation is created from the request it is executed to obtain the response.
	 *
	 * - The `ICanBoogie\HTTP\Request::dispatch` event of class
	 * `ICanBoogie\HTTP\Request\DispatchEvent` is fired with a {@link Response} object. Event hook
	 * might alter the response object to provide their response.
	 *
	 *
	 * Controllers chain
	 * -----------------
	 *
	 * The controllers chain is traversed until a controller returns a valid response. A response
	 * is considered valid if it has no client or server error when the request is not a XHR. This
	 * allows pages to be rendered even after a failed operation, so that an error message can be
	 * displayed.
	 *
	 * Note that this does not apply to '/api/' requests, which return a 404 response when they
	 * fail.
	 *
	 * @param Request $request
	 * @throws \ICanBoogie\Exception\HTTP
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
 */
class PopulateEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the dispatcher callbacks.
	 *
	 * @var array[string]mixed
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
	 * The HTTP response.
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
	 * The HTTP response.
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