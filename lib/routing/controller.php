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

class Controller
{
	/**
	 * Formats the specified namespace and name into a controller class name.
	 *
	 * @param string $namespace The namespace of the module defining the controller.
	 * @param string $name The name of the controller file.
	 *
	 * @return string
	 */
	static public function format_class_name($namespace, $name)
	{
		return $namespace . '\\' . ucfirst(camelize(strtr($name, '_', '-'))) . 'Controller';
	}

	/**
	 * The route to control.
	 *
	 * @var Route
	 */
	protected $route;

	/**
	 * Initializes the {@link $route} property.
	 *
	 * @param Route $route The route to control.
	 */
	public function __construct(Route $route)
	{
		$this->route = $route;
	}

	/**
	 * Controls the route and returns a response.
	 *
	 * @param Request $request
	 *
	 * @return Response
	 */
	public function __invoke(Request $request)
	{

	}
}