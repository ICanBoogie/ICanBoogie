<?php

namespace ICanBoogie;

$hooks = __NAMESPACE__ . '\Hooks::';

return [

	'events' => [

		'ICanBoogie\Render\TemplateResolver::alter' => $hooks . 'alter_template_resolver'

	],

	'prototypes' => [

		'ICanBoogie\Core::get_logger' => 'ICanBoogie\Logger::get_logger',
		'ICanBoogie\Core::get_session' => 'ICanBoogie\Session::get_session',
		'ICanBoogie\Core::lazy_get_routes' => $hooks . 'get_routes',
		'ICanBoogie\Core::lazy_get_template_engines' => $hooks . 'get_template_engines',
		'ICanBoogie\Core::lazy_get_template_resolver' => $hooks . 'get_template_resolver',
		'ICanBoogie\Core::lazy_get_renderer' => $hooks . 'get_renderer'

	]

];
