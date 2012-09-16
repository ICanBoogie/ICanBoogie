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

	public function offsetGet($offset)
	{
		return $this->offsetExists($offset) ? $this->routes[$offset] : array();
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

/**
 * A route.
 */
class Route
{
	static public $contextualize_callback;

	/**
	 * Contextualize the route.
	 *
	 * If the #{@link $contextualize_callback} class property is defined, the callback is used to
	 * contextualize the route, otherwise the route is returned as is.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	static public function contextualize($str)
	{
		return self::$contextualize_callback ? call_user_func(self::$contextualize_callback, $str) : $str;
	}

	static public $decontextualize_callback;

	/**
	 * Decontextualize the route.
	 *
	 * If the #{@link $decontextualize_callback} class property is defined, the callback is used to
	 * decontextualize the route, otherwise the route is returned as is.
	 *
	 * @param string $str
	 *
	 * @return string
	 */
	static public function decontextualize($str)
	{
		return self::$decontextualize_callback ? call_user_func(self::$decontextualize_callback, $str) : $str;
	}

	static private $parse_cache = array();

	/**
	 * Parses a route pattern and return an array of interleaved paths and parameters, parameters
	 * and the regular expression for the specified pattern.
	 *
	 * @param string $pattern The route pattern.
	 *
	 * @return array
	 */
	static public function parse($pattern)
	{
		if (isset(self::$parse_cache[$pattern]))
		{
			return self::$parse_cache[$pattern];
		}

		$regex = '#^';
		$interleave = array();
		$params = array();
		$n = 0;

		$parts = preg_split('#(:\w+|<(\w+:)?([^>]+)>)#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);

		for ($i = 0, $j = count($parts); $i < $j ;)
		{
			$part = $parts[$i++];

			$regex .= preg_quote($part, '#');
			$interleave[] = $part;

			if ($i == $j)
			{
				break;
			}

			$part = $parts[$i++];

			if ($part{0} == ':')
			{
				$identifier = substr($part, 1);
				$separator = $parts[$i];
				$selector = $separator ? '[^/\\' . $separator{0} . ']+' : '[^/]+';
			}
			else
			{
				$identifier = substr($parts[$i++], 0, -1);

				if (!$identifier)
				{
					$identifier = $n++;
				}

				$selector = $parts[$i++];
			}

			$regex .= '(' . $selector . ')';
			$interleave[] = array($identifier, $selector);
			$params[] = $identifier;
		}

		$regex .= '$#';

		return self::$parse_cache[$pattern] = array($interleave, $params, $regex);
	}

	/**
	 * Checks if a pathname matches a route pattern.
	 *
	 * @param string $pathname The pathname.
	 * @param string $pattern The pattern of the route.
	 * @param array $captured The parameters captured from the pathname.
	 *
	 * @return boolean
	 */
	static public function match($pathname, $pattern, &$captured=null)
	{
		$captured = array();
		$parsed = self::parse($pattern);

		list(, $params, $regex) = $parsed;

		#
		# $params is empty if the pattern is a plain string, in which case we can do a simple
		# string comparison.
		#

		$match = $params ? preg_match($regex, $pathname, $values) : $pathname === $pattern;

		if (!$match)
		{
			return false;
		}

		if ($params)
		{
			array_shift($values);

			$captured = array_combine($params, $values);
		}

		return true;
	}

	/**
	 * Returns a route formatted using a pattern and values.
	 *
	 * @param string $pattern The route pattern
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 *
	 * @return string The formatted route.
	 */
	static public function format($pattern, $values=null)
	{
		$url = '';
		$parsed = self::parse($pattern);

		if (is_array($values))
		{
			foreach ($parsed[0] as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values[$value[0]]) : $value;
			}
		}
		else
		{
			foreach ($parsed[0] as $i => $value)
			{
				$url .= ($i % 2) ? urlencode($values->$value[0]) : $value;
			}
		}

		return $url;
	}

	/**
	 * Checks if the given string is a route pattern.
	 *
	 * @param string $pattern
	 *
	 * @return bool `true` if the given pattern is a route pattern, `false` otherwise.
	 */
	static public function is_pattern($pattern)
	{
		return (strpos($pattern, '<') !== false) || (strpos($pattern, ':') !== false);
	}

	/**
	 * Identifier of the route.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Pattern of the route.
	 *
	 * @var string
	 */
	public $pattern;

	/**
	 * Redirect location.
	 *
	 * If the property is defined the route is considered an alias.
	 *
	 * @var string
	 */
	public $location;

	/**
	 * Class of the controller.
	 *
	 * @var string
	 */
	public $class;

	/**
	 * Callback of the controller.
	 *
	 * @var callable
	 */
	public $callback;

	/**
	 * Request methods accepted by the route.
	 *
	 * @var string
	 */
	public $via;

	public function __construct($pattern, array $properties)
	{
		$this->pattern = $pattern;

		foreach ($properties as $property => $value)
		{
			$this->$property = $value;
		}
	}

	/**
	 * Map the route and return its response.
	 *
	 * @param HTTP\Request $request
	 *
	 * @return HTTP\Response
	 */
	public function __invoke(HTTP\Request $request)
	{
		$response = new HTTP\Response
		(
			200, array
			(
				'Content-Type' => 'text/html; charset=utf-8'
			)
		);

		$rc = null;
		$callback = $this->callback;

		#
		# COMPAT: we only use 'callback' now, and check if its a class.
		#
		if ($this->class)
		{
			$callback = $this->class;
		}

		/*
		if (empty($route['block']) && empty($route['callback']) && empty($route['class']))
		{
			var_dump($route);

			throw new \InvalidArgumentException(format
			(
				'The %property property is required for route %id in %file.', array
				(
					'property' => 'callback',
					'id' => $id,
					'file' => $path . 'config/routes.php'
				)
			));
		}
		*/

		if (isset($this->controller))
		{
			$controller_class = $this->controller;
			$controller = new $controller_class($this);

			$request->route = $this; // FIXME-20120828: isn't this supposed to be set be the dispatcher ?

			$rc = $controller($request);
		}
		else
		{
			if (is_string($callback) && class_exists($callback, true))
			{
				$controller = new $callback($request, $this);

				$rc = $controller($request, $response, $this);
			}
			else if (!$this->callback)
			{
				\ICanBoogie\log_error('Route has no callback: \1', array($this));
			}
			else
			{
				$rc = call_user_func($this->callback, $request, $response, $this);
			}
		}

		if ($rc === null)
		{
			return;
		}

		if ($rc instanceof HTTP\Response)
		{
			$response = $rc;
		}
		else
		{
			$response->body = $rc;
		}

		return $response;
	}
}

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
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\Routes $target, array $properties)
	{
		parent::__construct($target, 'collect:before', $properties);
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
	 * @param array $properties
	 */
	public function __construct(\ICanboogie\Routes $target, array $properties)
	{
		parent::__construct($target, 'collect', $properties);
	}
}