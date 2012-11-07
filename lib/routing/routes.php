<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;

/**
 * The route collection.
 *
 * Initial routes are collected from the "routes" config.
 *
 *
 *
 * Event: ICanBoogie\Routes::collect:before
 * ----------------------------------------
 *
 * Third parties may use the event {@link Routes\BeforeCollectEvent} to alter the configuration
 * fragments before they are synthesized. The event is fired during {@link __construct()}.
 *
 *
 *
 * Event: ICanBoogie\Routes::collect
 * ---------------------------------
 *
 * Third parties may use the event {@link Routes\CollectEvent} to alter the routes read from
 * the configuration. The event is fired during {@link __construct()}.
 */
class Routes implements \IteratorAggregate, \ArrayAccess
{
	static protected $instance;

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return \ICanBoogie\Routes
	 */
	static public function get()
	{
		if (!self::$instance)
		{
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Dispatches the request among the routes.
	 *
	 * @param Request $request
	 *
	 * @return \ICanBoogie\HTTP\Response|null
	 */
	static public function dispatch_request(Request $request)
	{
		$path = rtrim(Route::decontextualize($request->normalized_path), '/');

		if (!$path) // we trim the '/' but we need '/' for index TODO-20120911: better solution
		{
			$path = '/';
		}

		$route = static::get()->find($path, $captured, $request->method);

		if (!$route)
		{
			return;
		}

		$request->path_params = $captured + $request->path_params;
		$request->params = $captured + $request->params;

		if ($route->location)
		{
			return new Response(302, array('Location' => Route::contextualize($route->location)));
		}

		return $route($request);
	}

	protected $routes;

	protected $instances = array();

	/**
	 * Collects routes definitions from the "routes" config.
	 */
	protected function __construct()
	{
		$this->routes = $this->collect();
	}

	public function getIterator()
	{
		return new \ArrayIterator($this->routes);
	}

	public function offsetExists($offset)
	{
		return isset($this->routes[$offset]);
	}

	public function offsetGet($id)
	{
		if (isset($this->instances[$id]))
		{
			return $this->instances[$id];
		}

		if (!$this->offsetExists($id))
		{
			throw new RouteNotDefined($id);
		}

		$properties = $this->routes[$id];

		$class = 'ICanBoogie\Route';

		if (isset($properties['class']))
		{
			$class = $properties['class'];
		}

		return $this->instances[$id] = new $class($properties['pattern'], $properties);
	}

	/**
	 * Adds or replaces a route.
	 *
	 * @param mixed $offset The identifier of the route.
	 * @param array $route The route definition.
	 *
	 * @throws \LogicException if the route definition is invalid.
	 *
	 * @see ArrayAccess::offsetSet()
	 */
	public function offsetSet($offset, $route)
	{
		if (empty($route['pattern']))
		{
			throw new \LogicException(format
			(
				"Route %id has no pattern. !route", array
				(
					'id' => $id,
					'route' => $route
				)
			));
		}

		$this->routes[$offset] = $route + array
		(
			'via' => Request::METHOD_ANY
		);
	}

	static public function add($id, $definition)
	{
		$routes = static::get();
		$routes[$id] = $definition;
	}

	/**
	 * Removes a route.
	 *
	 * @param string $offset The identifier of the route.
	 *
	 * @see ArrayAccess::offsetUnset()
	 */
	public function offsetUnset($offset)
	{
		unset($this->routes[$offset]);
	}

	/**
	 * Returns route collection.
	 *
	 * The collection is built in 4 steps:
	 *
	 * 1. Routes are traversed to add the `module` and `via` properties. If the route is defined
	 * by a module the `module` property is set to the id of the module, otherwise it is set
	 * to `null`. The `via` property is set to {@link Request::METHOD_ANY} if it is not defined.
	 *
	 * 2. The {@link Routes\BeforeCollectEvent} event is fired.
	 *
	 * @return array
	 */
	protected function collect()
	{
		global $core;

		$collection = $this;

		return $core->configs->synthesize
		(
			'routes', function($fragments) use($collection)
			{
				global $core;

				$module_roots = array();

				foreach ($core->modules->descriptors as $module_id => $descriptor)
				{
					$module_roots[$descriptor[Module::T_PATH]] = $module_id;
				}

				foreach ($fragments as $module_root => &$fragment)
				{
					$module_id = isset($module_roots[$module_root]) ? $module_roots[$module_root] : null;

					foreach ($fragment as $route_id => &$route)
					{
						$route += array
						(
							'via' => Request::METHOD_ANY,
							'module' => $module_id
						);
					}
				}

				unset($fragment);
				unset($route);

				new Routes\BeforeCollectEvent($collection, array('fragments' => &$fragments));

				$routes = array();

				foreach ($fragments as $path => $fragment)
				{
					foreach ($fragment as $id => $route)
					{
						$routes[$id] = $route + array
						(
							'pattern' => null
						);
					}
				}

				new Routes\CollectEvent($collection, array('routes' => &$routes));

				return $routes;
			}
		);
	}

	/**
	 * Search for a route matching the specified pathname and method.
	 *
	 * @param string $uri The URI to match.
	 * @param array|null $captured The parameters captured from the URI.
	 * @param string $method One of HTTP\Request::METHOD_* methods.
	 * @param string $namespace Namespace restriction.
	 *
	 * @return Route
	 */
	public function find($uri, &$captured=null, $method=Request::METHOD_ANY, $namespace=null)
	{
		$captured = array();

		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		$found = null;
		$pattern = null;

		foreach ($this->routes as $id => $route)
		{
			$pattern = $route['pattern'];

			if ($namespace && strpos($pattern, $namespace) !== 0)
			{
				continue;
			}

			if (!Route::match($uri, $pattern, $captured))
			{
				continue;
			}

			$route_method = $route['via'];

			if (is_array($route_method))
			{
				if (in_array($method, $route_method))
				{
					$found = true;
					break;
				}
			}
			else
			{
				if ($route_method === Request::METHOD_ANY || $route_method === $method)
				{
					$found = true;
					break;
				}
			}
		}

		if (!$found)
		{
			return;
		}

		return new Route
		(
			$pattern, $route + array
			(
				'id' => $id
			)
		);
	}
}

/*
 * EXCEPTIONS
 */

/**
 * Exception thrown when a route does not exists.
 */
class RouteNotDefined extends \Exception
{
	/**
	 * @param string $id Identifier of the route.
	 * @param int $code
	 * @param \Exception $previous
	 */
	public function __construct($id, $code=404, \Exception $previous)
	{
		parent::__construct("The route <q>$id</q> is not defined.", $code, $previous);
	}
}

/*
 * EVENTS
 */

namespace ICanBoogie\Routes;

/**
 * Event class for the `ICanBoogie\Events::collect:before` event.
 *
 * Third parties may use this event to alter the configuration fragments before they are
 * synthesized.
 */
class BeforeCollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the configuration fragments.
	 *
	 * @var array
	 */
	public $fragments;

	/**
	 * The event is constructed with the type `alter:before`.
	 *
	 * @param \ICanBoogie\Routes $target The routes collection.
	 * @param array $payload
	 */
	public function __construct(\ICanBoogie\Routes $target, array $payload)
	{
		parent::__construct($target, 'collect:before', $payload);
	}
}

/**
 * Event class for the `ICanBoogie\Events::collect` event.
 *
 * Third parties may use this event to alter the routes read from the configuration.
 */
class CollectEvent extends \ICanBoogie\Event
{
	/**
	 * Reference to the routes.
	 *
	 * @var array[string]array
	 */
	public $routes;

	/**
	 * The event is constructed with the type `collect`.
	 *
	 * @param \ICanboogie\Routes $target The routes collection.
	 * @param array $payload
	 */
	public function __construct(\ICanboogie\Routes $target, array $payload)
	{
		parent::__construct($target, 'collect', $payload);
	}
}