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

/**
 * Routes collected from the "routes" config or added by the user.
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

	protected $routes;

	/**
	 * Collects routes definitions from the "routes" config.
	 */
	protected function __construct()
	{
		global $core;

		$this->routes = $core->configs->synthesize
		(
			'routes', function($fragments)
			{
				global $core;

				$paths = array();

				foreach ($core->modules->descriptors as $module_id => $descriptor)
				{
					$paths[$descriptor[Module::T_PATH]] = $module_id;
				}

				$routes = array();

				foreach ($fragments as $path => $fragment)
				{
					$module_id = isset($paths[$path]) ? $paths[$path] : null;

					foreach ($fragment as $id => $route)
					{
						if ($id{0} === '!')
						{
							$id = "$module_id:admin/" . substr($id, 1);
						}
						else if ($id{0} === ':' && $module_id)
						{
							$id = $module_id . $id;
						}

						$routes[$id] = $route + array
						(
							'pattern' => null,
							'via' => Request::METHOD_ANY,
							'module' => $module_id
						);
					}
				}

				return $routes;
			}
		);
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
	 * Search for a route matching the specified pathname and method.
	 *
	 * @param string $uri
	 * @param string $method One of HTTP\Request::METHOD_* methods.
	 * @param string $namespace Namespace restriction.
	 *
	 * @return Route
	 */
	public function find($uri, $method=Request::METHOD_ANY, $namespace=null)
	{
		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		$found = null;
		$pattern = null;

		foreach ($this->routes as $id => $route)
		{
			if ($id{0} === '!')
			{
				continue;
			}

			$pattern = $route['pattern'];

			if ($namespace && strpos($pattern, $namespace) !== 0)
			{
				continue;
			}

			$match = Route::match($uri, $pattern);

			if (!$match)
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
				'id' => $id,
				'path_params' => is_array($match) ? $match : array()
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

			$regex .= $part;
			$interleave[] = $part;

			if ($i == $j)
			{
				break;
			}

			$part = $parts[$i++];

			if ($part{0} == ':')
			{
				$identifier = substr($part, 1);
				$selector = '[^/]+';
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

	static public function match($pathname, $pattern)
	{
		$parsed = self::parse($pattern);

		list(, $params, $regex) = $parsed;

		$match = preg_match($regex, $pathname, $values);

		if (!$match)
		{
			return false;
		}
		else if (!$params)
		{
			return true;
		}

		array_shift($values);

		return array_combine($params, $values);
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

	/**
	 * Parameters captured from the URL path using the route pattern.
	 *
	 * @var array
	 */
	public $path_params;

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
		$response = new HTTP\Response;
		$rc = null;

		if ($this->callback)
		{
			$rc = call_user_func($this->callback, $request, $response, $this);
		}
		else if ($this->class)
		{
			$controller_class = $this->class;
			$controller = new $controller_class($request, $this);

			$rc = $controller($request, $response, $this);
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