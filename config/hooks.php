<?php

return [

	'events' => [

		'ICanBoogie\Session::start' => 'ICanBoogie\Debug::restore_logs'
	],

	'prototypes' => [

		'ICanBoogie\Core::get_session' => 'ICanBoogie\Session::get_session'
	]
];