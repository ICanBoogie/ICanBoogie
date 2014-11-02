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

	'error_handler' => __NAMESPACE__ . '\Debug::error_handler',
	'exception_handler' => __NAMESPACE__ . '\Debug::exception_handler',

	'session' => [

		'name' => 'ICanBoogie',
		'domain' => null

	]
];
