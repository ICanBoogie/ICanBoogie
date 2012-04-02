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

/**
 * Returns requests dispatcher.
 *
 * @return Dispatcher
 */
function get_dispatcher()
{
	static $dispatcher;

	if (!$dispatcher)
	{
		$dispatcher = new Dispatcher;
	}

	return $dispatcher;
}

/**
 * Dispatches a request.
 *
 * @param Request $request
 *
 * @return Response
 */
function dispatch(Request $request)
{
	$dispatcher = get_dispatcher();

	return $dispatcher($request);
}
