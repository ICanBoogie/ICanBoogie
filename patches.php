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

/*
 * Patches the `get_dispatcher` helper to initialize the dispatcher with the operation and route
 * dispatchers.
 */
Helpers::patch('get_dispatcher', function() {

	static $dispatcher;

	if (!$dispatcher)
	{
		$dispatcher = new Dispatcher([

			'operation' => 'ICanBoogie\Operation\Dispatcher',
			'route' => 'ICanBoogie\Routing\Dispatcher'

		]);

		new Dispatcher\AlterEvent($dispatcher);
	}

	return $dispatcher;

});
