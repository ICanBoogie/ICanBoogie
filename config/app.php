<?php

namespace ICanBoogie;

return [

	'cache catalogs' => false,
	'cache configs' => false,
	'cache modules' => false,

	/**
	 * Specifies the storage engine for synthesized configurations.
	 *
	 * The value may be a class name or a callable that would create the instance. The callable
	 * should have the following signature:
	 *
	 * ```
	 * callable(\ICanBoogie\Core $app): \ICanBoogie\Storage\Storage
	 * ```
	 */
	'storage_for_configs' => Hooks::class . '::create_storage_for_configs',

	/**
	 * Specifies the storage engine for variables.
	 *
	 * The value may be a class name or a callable that would create the instance. The callable
	 * should have the following signature:
	 *
	 * ```
	 * callable(\ICanBoogie\Core $app): \ICanBoogie\Storage\Storage
	 * ```
	 */
	'storage_for_vars' => Hooks::class . '::create_storage_for_vars',

	'repository' => '/repository',
	'repository.temp' => '/repository/tmp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'error_handler' => Debug::class. '::error_handler',
	'exception_handler' => Debug::class. '::exception_handler',

	'session' => [

		SessionOptions::OPTION_NAME => 'ICanBoogie'

	]

];
