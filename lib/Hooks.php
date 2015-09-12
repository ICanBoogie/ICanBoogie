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

		if (!APCStorage::is_available())
		{
			return $storage;
		}

		return new StorageCollection([

			new APCStorage('icanboogie_cache_configs' . sha1(__DIR__)),
			$storage

		]);
	}
}
