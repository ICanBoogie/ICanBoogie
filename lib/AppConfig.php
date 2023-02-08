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

use function rtrim;

use const DIRECTORY_SEPARATOR;

final class AppConfig
{
    public const DEFAULT_DIRECTORY_FOR_REPOSITORY = 'repository';
    public const DEFAULT_DIRECTORY_FOR_CACHE = 'cache';
    public const DEFAULT_DIRECTORY_FOR_CACHE_CONFIGS = 'configs';
    public const DEFAULT_DIRECTORY_FOR_FILES = 'files';
    public const DEFAULT_DIRECTORY_FOR_TMP = 'tmp';
    public const DEFAULT_DIRECTORY_FOR_VARS = 'var';

    /**
     * @param array<string, mixed> $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array); // @phpstan-ignore-line
    }

    public readonly string $repository;
    public readonly string $repository_cache;
    public readonly string $repository_cache_configs;
    public readonly string $repository_files;
    public readonly string $repository_tmp;
    public readonly string $repository_var;

    /**
     * @param bool $cache_catalogs
     *     Whether message catalogs should be cached.
     *
     * @param bool $cache_configs
     *     Whether configurations should be cached.
     *
     * @param callable|null $storage_for_config
     *     The storage engine for configurations.
     *
     *     The value may be a class name or a callable that would create the instance. The callable
     *     should have the following signature:
     *
     *     ```
     *     callable(\ICanBoogie\Application $app): \ICanBoogie\Storage\Storage
     *     ```
     *
     * @param callable|null $storage_for_vars
     *     The storage engine for variables.
     *
     *     The value may be a class name or a callable that would create the instance. The callable
     *     should have the following signature:
     *
     *     ```
     *     callable(\ICanBoogie\Application $app): \ICanBoogie\Storage\Storage
     *     ```
     *
     * @param callable|null $error_handler
     *     The error handler of the application.
     *
     * @param callable|null $exception_handler
     *     The exception handler of the application.
     *
     * @param string|null $repository
     *     The path to the _repository_ directory.
     *
     *     Defaults to: `repository/`
     *
     * @param string|null $repository_cache
     *     The path to the _cache_ directory.
     *
     *     The directory does not have to be a sub-folder of `$repository`.
     *
     *     Defaults to: `$repository/cache/`.
     *
     * @param string|null $repository_cache_configs
     *     The path to the _cache config_ directory.
     *
     *     The directory does not have to be a sub-folder of `$repository`.
     *
     *     Defaults to: `$repository_cache/configs/`.
     *
     * @param string|null $repository_files
     *     The path to the _files_ directory.
     *
     *     The directory does not have to be a sub-folder of `$repository`.
     *
     *     Defaults to: `$repository/files/`.
     *
     * @param string|null $repository_tmp
     *     The path to the _tmp_ directory.
     *
     *     The directory does not have to be a sub-folder of `$repository`.
     *
     *     Defaults to: `$repository/tmp/`.
     *
     * @param string|null $repository_var
     *     The path to the _var_ directory.
     *
     *     The directory does not have to be a sub-folder of `$repository`.
     *
     *     Defaults to: `$repository/var/`.
     *
     * @phpstan-param array<SessionOptions::OPTION_*, mixed> $session
     *     Session parameters.
     */
    public function __construct(
        public readonly bool $cache_catalogs = false,
        public readonly bool $cache_configs = false,
        public readonly mixed $storage_for_config = null,
        public readonly mixed $storage_for_vars = null,
        public readonly mixed $error_handler = null,
        public readonly mixed $exception_handler = null,
        ?string $repository = null,
        ?string $repository_cache = null,
        ?string $repository_cache_configs = null,
        ?string $repository_files = null,
        ?string $repository_tmp = null,
        ?string $repository_var = null,
        public readonly array $session = [],
    ) {
        $this->repository = $repository = $this->ensure_trailing_separator(
            $repository ?? self::DEFAULT_DIRECTORY_FOR_REPOSITORY
        );

        $this->repository_cache = $repository_cache = $this->ensure_trailing_separator(
            $repository_cache ?? $repository . self::DEFAULT_DIRECTORY_FOR_CACHE
        );

        $this->repository_cache_configs = $this->ensure_trailing_separator(
            $repository_cache_configs ?? $repository_cache . self::DEFAULT_DIRECTORY_FOR_CACHE_CONFIGS
        );

        $this->repository_files = $this->ensure_trailing_separator(
            $repository_files ?? $repository . self::DEFAULT_DIRECTORY_FOR_FILES
        );

        $this->repository_tmp = $this->ensure_trailing_separator(
            $repository_tmp ?? $repository . self::DEFAULT_DIRECTORY_FOR_TMP
        );

        $this->repository_var = $this->ensure_trailing_separator(
            $repository_var ?? $repository . self::DEFAULT_DIRECTORY_FOR_VARS
        );
    }

    private function ensure_trailing_separator(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
