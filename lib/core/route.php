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

class Route
{
	public static $contextualize_callback;

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
	public static function contextualize($str)
	{
		return self::$contextualize_callback ? call_user_func(self::$contextualize_callback, $str) : $str;
	}

	public static $decontextualize_callback;

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
	public static function decontextualize($str)
	{
		return self::$decontextualize_callback ? call_user_func(self::$decontextualize_callback, $str) : $str;
	}

	protected static $routes = array();

	private static $constructed;

	/**
	 * Returns the routes defined using the configuration system or added using the add() method.
	 *
	 * @return array
	 */
	public static function routes()
	{
		global $core;

		if (!self::$constructed)
		{
			self::$constructed = true;

			self::$routes += $core->configs->synthesize('routes', array(__CLASS__, 'routes_constructor'));
		}

		return self::$routes;
	}

	/**
	 * Indexes routes, filtering out the route definitions which don't start with '/'
	 *
	 * @param array $fragments Configiration fragments
	 *
	 * @return array
	 */
	public static function routes_constructor(array $fragments)
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
				else if (empty($route['pattern']))
				{
					throw new \LogicException(t
					(
						"Route %route_id has no pattern in %path. !route", array
						(
							'%route_id' => $id,
							'%path' => $path,
							'!route' => $route
						)
					));
				}
				else if ($id{0} === ':' && $module_id)
				{
					$id = $module_id . $id;
				}

				$routes[$id] = $route + array
				(
					'pattern' => null,
					'via' => 'any'
				);
			}
		}

		return $routes;
	}

	/**
	 * Adds or replaces a route, or a set of routes,
	 *
	 * @param mixed $pattern The pattern for the route to add or replace, or an array of
	 * pattern/route.
	 * @param array $route The route definition for the pattern, or nothing if the pattern is
	 * actually a set of routes.
	 */
	public static function add($id, array $route=array())
	{
		if (is_array($id))
		{
			foreach ($id as $i => $route)
			{
				static::add($i, $route);
			}

			return;
		}

		if (empty($route['pattern']))
		{
			throw new \LogicException
			(
				format
				(
					"Route %id has no pattern. !route", array
					(
						'id' => $id,
						'route' => $route
					)
				)
			);
		}

		self::$routes[$id] = $route + array
		(
			'via' => 'any'
		);
	}

	/**
	 * Removes a route from the routes using its pattern.
	 *
	 * @param string $pattern The pattern for the route to remove.
	 */
	public static function remove($pattern)
	{
		self::routes();

		unset(self::$routes[$pattern]);
	}

	private static $parse_cache = array();

	/**
	 * Parses a route pattern and return an array of interleaved paths and parameters, parameters
	 * and the regular expression for the specified pattern.
	 *
	 * @param string $pattern The route pattern.
	 *
	 * @return array
	 */
	public static function parse($pattern)
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

	public static function match($uri, $pattern)
	{
		$parsed = self::parse($pattern);

		list(, $params, $regex) = $parsed;

		$match = preg_match($regex, $uri, $values);

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

	public static function find($uri, $method='any', $namespace=null)
	{
		$routes = self::routes();
		$namespace_length = 0;

		if ($namespace)
		{
			$namespace = '/' . $namespace . '/';
		}

		foreach ($routes as $id => $route)
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

			$match = self::match($uri, $pattern);

			if (!$match)
			{
				continue;
			}

			$route_method = $route['via'];

			if (is_array($route_method))
			{
				if (in_array($method, $route_method))
				{
					return array($route, $match, $pattern, $id);
				}
			}
			else
			{
				if ($route_method === 'any' || $route_method === $method)
				{
					return array($route, $match, $pattern, $id);
				}
			}
		}
	}

	/**
	 * Returns a route formated using a pattern and values.
	 *
	 * @param string $pattern The route pattern
	 * @param mixed $values The values to format the pattern, either as an array or an object.
	 *
	 * @return string The formated route.
	 */
	public static function format($pattern, $values=null)
	{
		if (is_array($values))
		{
			$values = (object) $values;
		}

		$url = '';
		$parsed = self::parse($pattern);

		foreach ($parsed[0] as $i => $value)
		{
			$url .= ($i % 2) ? urlencode($values->$value[0]) : $value;
		}

		return $url;
	}

	/**
	 * Checks if the given string is a route pattern.
	 *
	 * @param string $pattern
	 *
	 * @return true is the given pattern is a route pattern, false otherwise.
	 */
	public static function is_pattern($pattern)
	{
		return (strpos($pattern, '<') !== false) || (strpos($pattern, ':') !== false);
	}
}