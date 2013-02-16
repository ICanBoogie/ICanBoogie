<?php

return array
(
	'cache bootstrap' => false,
	'cache catalogs' => false,
	'cache configs' => false,
	'cache modules' => false,

	'config constructors' => array
	(
		'debug' => array('ICanBoogie\Debug::synthesize_config'),
		'events' => array('ICanBoogie\Events::synthesize_config', 'hooks'),
		'prototypes' => array('ICanBoogie\Prototype::synthesize_config', 'hooks')
	),

	'config paths' => array
	(

	),

	'connections' => array
	(

	),

	'locale paths' => array
	(

	),

	'modules paths' => array
	(

	),

	'repository' => '/repository',
	'repository.temp' => '/repository/tmp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'session' => array
	(
		'name' => 'ICanBoogie'
	)
);