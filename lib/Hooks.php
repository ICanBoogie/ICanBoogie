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

use function sha1;
use function substr;

final class Hooks
{
    /**
     * Creates a storage engine for synthesized configurations.
     *
     * If APC is available the method returns a storage collection or {@link APCStorage} and
     * {@link FileStorage}, otherwise a {@link FileStorage} is returned.
     * {@link FileStorage\Adapter\PHPAdapter} is used as adapter for {@link FileStorage}.
     *
     * @return Storage<string, mixed>
     */
    public static function create_storage_for_configs(Application $app): Storage
    {
        $directory = $app->config->repository_cache_configs;
        $storage = new FileStorage($directory, new FileStorage\Adapter\PHPAdapter());

        return self::with_apc_storage($storage, 'icanboogie:config:');
    }

    /**
     * Creates a storage engine for synthesized configurations.
     *
     * If APC is available the method returns a storage collection or {@link APCStorage} and
     * {@link FileStorage}, otherwise a {@link FileStorage} is returned.
     *
     * @return Storage<string, mixed>
     */
    public static function create_storage_for_vars(Application $app): Storage
    {
        $directory = $app->config->repository_var;
        $storage = new FileStorage($directory);

        return self::with_apc_storage($storage, 'icanboogie:vars:');
    }

    /**
     * If APC is available the method return a storage collection with a {@link APCStorage}
     * instance and the specified storage instance.
     *
     * @param Storage<string, mixed> $storage
     *
     * @return Storage<string, mixed>
     */
    private static function with_apc_storage(Storage $storage, string $prefix): Storage
    {
        if (!APCStorage::is_available()) {
            return $storage;
        }

        return new StorageCollection([ new APCStorage(self::make_apc_prefix() . $prefix), $storage ]);
    }

    /**
     * Makes an APC prefix for the application.
     */
    private static function make_apc_prefix(): string
    {
        return substr(sha1(ROOT), 0, 8) . ':';
    }

    /*
     * Events
     */

    /**
     * Clears configurations cache.
     */
    public static function on_clear_cache(Application\ClearCacheEvent $event): void
    {
        $event->app->storage_for_configs->clear();
        $event->cleared('app.storage_for_configs');
    }
}
