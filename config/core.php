<?php

namespace ICanBoogie;

return [

	'cache bootstrap' => false,
	'cache catalogs' => false,
	'cache configs' => false,
	'cache modules' => false,

	'repository' => '/repository',
	'repository.temp' => '/repository/tmp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'error_handler' => Debug::class. '::error_handler',
	'exception_handler' => Debug::class. '::exception_handler',

	'session' => [

		'name' => 'ICanBoogie',
		'domain' => null

	]
];
