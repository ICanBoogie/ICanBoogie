<?php

return array
(
	'api:core/aloha' => array
	(
		'pattern' => '/api/core/aloha',
		'controller' => 'ICanBoogie\AlohaOperation'
	),

	'api:core/ping' => array
	(
		'pattern' => '/api/core/ping',
		'controller' => 'ICanBoogie\PingOperation'
	)
);