<?php

return array
(
	'cache bootstrap' => false,
	'cache catalogs' => false,
	'cache configs' => false,
	'cache modules' => false,

	'classes aliases' => array
	(

	),

	'config constructors' => array
	(
		'autoload' => array('merge'),
		'debug' => array('ICanBoogie\Debug::synthesize_config'),
		'events' => array('ICanBoogie\Events::synthesize_config', 'hooks'),
		'prototypes' => array('ICanBoogie\Prototype::synthesize_config', 'hooks')
	),

	'connections' => array
	(

	),

	'modules' => array
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