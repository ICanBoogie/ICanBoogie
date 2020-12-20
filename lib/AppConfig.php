<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use LogicException;

use function array_values;
use function file_exists;
use function realpath;
use function str_replace;

use const DIRECTORY_SEPARATOR;

final class AppConfig
{
	/**
	 * Whether message catalogs should be cached.
	 */
	public const CACHE_CATALOGS = 'cache catalogs';

	/**
	 * Whether synthesized configuration fragments should be cached.
	 */
	public const CACHE_CONFIGS = 'cache configs';

	/**
	 * Whether module descriptors should be cached.
	 */
	public const CACHE_MODULES = 'cache modules';

	/**
	 * Specify the storage engine for synthesized configurations.
	 *
	 * The value may be a class name or a callable that would create the instance. The callable
	 * should have the following signature:
	 *
	 * ```
	 * callable(\ICanBoogie\Application $app): \ICanBoogie\Storage\Storage
	 * ```
	 */
	public const STORAGE_FOR_CONFIGS = 'storage_for_configs';

	/**
	 * Specify the storage engine for variables.
	 *
	 * The value may be a class name or a callable that would create the instance. The callable
	 * should have the following signature:
	 *
	 * ```
	 * callable(\ICanBoogie\Application $app): \ICanBoogie\Storage\Storage
	 * ```
	 */
	public const STORAGE_FOR_VARS = 'storage_for_vars';

	/**
	 * Specify the error handler of the application.
	 */
	public const ERROR_HANDLER = 'error_handler';

	/**
	 * Specify the exception handler of the application.
	 */
	public const EXCEPTION_HANDLER = 'exception_handler';

	/**
	 * Specify the path to the _repository_ directory.
	 */
	public const REPOSITORY = 'repository';

	/**
	 * Specify the path to the _cache_ directory.
	 *
	 * The directory does not have to be a sub-folder of `REPOSITORY`.
	 *
	 * **Note**: `{repository}` is replaced by the directory specified by `REPOSITORY`.
	 */
	public const REPOSITORY_CACHE = 'repository/cache';

	/**
	 * Specify the path to the _cache/configs_ directory.
	 *
	 * The directory does not have to be a sub-folder of `REPOSITORY`.
	 *
	 * **Note**: `{repository}` is replaced by the directory specified by `REPOSITORY`.
	 */
	public const REPOSITORY_CACHE_CONFIGS = 'repository/cache/configs';

	/**
	 * Specify the path to the _files_ directory.
	 *
	 * The directory does not have to be a sub-folder of `REPOSITORY`.
	 *
	 * **Note**: `{repository}` is replaced by the directory specified by `REPOSITORY`.
	 */
	public const REPOSITORY_FILES = 'repository/files';

	/**
	 * Specify the path to the _tmp_ directory.
	 *
	 * The directory does not have to be a sub-folder of `REPOSITORY`.
	 *
	 * **Note**: `{repository}` is replaced by the directory specified by `REPOSITORY`.
	 */
	public const REPOSITORY_TMP = 'repository/tmp';

	/**
	 * Specify the path to the _var_ directory.
	 *
	 * The directory does not have to be a sub-folder of `REPOSITORY`.
	 *
	 * **Note**: `{repository}` is replaced by the directory specified by `REPOSITORY`.
	 */
	public const REPOSITORY_VARS = 'repository/var';

	/**
	 * Specify session parameters.
	 */
	public const SESSION = 'session';

	/**
	 * Synthesize the `app` config, from `app` fragments.
	 *
	 * @param array<array<string, mixed>> $fragments
	 *
	 * @return array<string, mixed>
	 */
	static public function synthesize(array $fragments): array
	{
		$config = array_merge_recursive(...array_values($fragments));

		self::normalize_repository($config);

		return $config;
	}

	/**
	 * Normalize `REPOSITORY*` items.
	 *
	 * @param array<string, mixed> $config
	 */
	static private function normalize_repository(array &$config): void
	{
		static $interpolatable = [

			self::REPOSITORY_CACHE,
			self::REPOSITORY_CACHE_CONFIGS,
			self::REPOSITORY_FILES,
			self::REPOSITORY_TMP,
			self::REPOSITORY_VARS

		];

		$repository = &$config[self::REPOSITORY];

		if (!file_exists($repository))
		{
			throw new LogicException("The directory does not exists: $repository");
		}

		$repository = realpath($repository);

		assert(is_string($repository));

		foreach ($interpolatable as $item)
		{
			$config[$item] = str_replace('{repository}', $repository, $config[$item]) . DIRECTORY_SEPARATOR;
		}

		$repository .= DIRECTORY_SEPARATOR;
	}
}
