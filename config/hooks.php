<?php

return array
(
	'events' => array
	(
		'ICanBoogie\Session::start' => 'ICanBoogie\Debug::restore_logs'
	),

	'prototypes' => array
	(
		'ICanBoogie\Core::get_session' => 'ICanBoogie\Session::get_session'
	)
);