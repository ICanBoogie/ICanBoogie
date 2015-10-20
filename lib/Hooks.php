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

use ICanBoogie\Storage\APCStorage;
use ICanBoogie\Storage\FileStorage;
use ICanBoogie\Storage\Storage;
use ICanBoogie\Storage\StorageCollection;

class Hooks
{
	/**
	 * If APC is available the method return a storage collection with a {@link APCStorage}
	 * instance and the specified storage instance.
	 *
	 * @param Storage $storage
	 * @param string $prefix Prefix for the {@link APCStorage} instance.
	 *
	 * @return Storage|StorageCollection
	 */
	static private function with_apc_storage(Storage $storage, $prefix)
	{
		if (!APCStorage::is_available())
		{
			return $storage;
		}

		return new StorageCollection([ new APCStorage(self::make_apc_prefix() . $prefix), $storage ]);
	}

	/**
	 * Makes an APC prefix for the application.
	 *
	 * @return string
	 */
	static public function make_apc_prefix()
	{
		return substr(sha1(ROOT), 0, 8) . ':';
	}

	/**
	 * Creates a storage engine for synthesized configurations.
	 *
	 * If APC is available the method returns a storage collection or {@link APCStorage} and
	 * {@link FileStorage}, otherwise a {@link FileStorage} is returned.
	 *
	 * @param Core $app
	 *
	 * @return Storage
	 */
	static public function create_storage_for_configs(Core $app)
	{
		$storage = new FileStorage(REPOSITORY . 'cache' . DIRECTORY_SEPARATOR . 'configs');

		return self::with_apc_storage($storage, 'icanboogie:configs:');
	}

	/**
	 * Creates a storage engine for synthesized configurations.
	 *
	 * If APC is available the method returns a storage collection or {@link APCStorage} and
	 * {@link FileStorage}, otherwise a {@link FileStorage} is returned.
	 *
	 * @param Core $app
	 *
	 * @return Storage
	 */
	static public function create_storage_for_vars(Core $app)
	{
		$storage = new FileStorage(REPOSITORY . 'vars');

		return self::with_apc_storage($storage, 'icanboogie:vars:');
	}
}
