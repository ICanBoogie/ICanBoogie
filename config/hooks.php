<?php

namespace ICanBoogie;

$hooks = __NAMESPACE__ . '\Hooks::';

return [

	'prototypes' => [

		'ICanBoogie\Core::get_logger' => 'ICanBoogie\Logger::get_logger',
		'ICanBoogie\Core::get_session' => 'ICanBoogie\Session::get_session',
		'ICanBoogie\Core::lazy_get_routes' => $hooks . 'lazy_get_routes'

	]

];
