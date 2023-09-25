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
    public const DEFAULT_DIRECTORY_FOR_VAR = 'var';
    public const DEFAULT_DIRECTORY_FOR_CACHE = 'cache';
    public const DEFAULT_DIRECTORY_FOR_CACHE_CONFIGS = 'configs';
    public const DEFAULT_DIRECTORY_FOR_FILES = 'files';
    public const DEFAULT_DIRECTORY_FOR_LIB = 'lib';
    public const DEFAULT_DIRECTORY_FOR_TMP = 'tmp';

    /**
     * @param array<string, mixed> $an_array
     */
    public static function __set_state(array $an_array): self
    {
        return new self(...$an_array); // @phpstan-ignore-line
    }

    /**
     * This directory used to store variable data files.
     *
     * @var non-empty-string
     */

    public readonly string $var;

    /**
     * This directory used to store cache data.
     *
     * @var non-empty-string
     */
    public readonly string $var_cache;

    /**
     * This directory used to store cache config data.
     *
     * @var non-empty-string
     */
    public readonly string $var_cache_configs;

    /**
     * This directory used to store files.
     *
     * @var non-empty-string
     */
    public readonly string $var_files;

    /**
     * The directory used to store state information.
     *
     * @var non-empty-string
     */
    public readonly string $var_lib;

    /**
     * This directory used to store temporary files.
     *
     * @var non-empty-string
     */
    public readonly string $var_tmp;

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
     * @param non-empty-string|null $var
     *     The path to the _var_ directory.
     *
     *     Defaults to: `var/`
     *
     * @param non-empty-string|null $var_cache
     *     The path to the _cache_ directory.
     *
     *     The directory does not have to be a sub-folder of `$var`.
     *
     *     Defaults to: `$var/cache/`.
     *
     * @param non-empty-string|null $var_cache_configs
     *     The path to the _cache config_ directory.
     *
     *     The directory does not have to be a sub-folder of `$var`.
     *
     *     Defaults to: `$var_cache/configs/`.
     *
     * @param non-empty-string|null $var_files
     *     The path to the _files_ directory.
     *
     *     The directory does not have to be a sub-folder of `$var`.
     *
     *     Defaults to: `$var/files/`.
     *
     * @param non-empty-string|null $var_tmp
     *     The path to the _tmp_ directory.
     *
     *     The directory does not have to be a sub-folder of `$var`.
     *
     *     Defaults to: `$var/tmp/`.
     *
     * @param non-empty-string|null $var_lib
     *     The path to the _var_ directory.
     *
     *     The directory does not have to be a sub-folder of `$var`.
     *
     *     Defaults to: `$var/var/`.
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
        ?string $var = null,
        ?string $var_cache = null,
        ?string $var_cache_configs = null,
        ?string $var_files = null,
        ?string $var_lib = null,
        ?string $var_tmp = null,
        public readonly array $session = [],
    ) {
        $this->var = $var = $this->ensure_trailing_separator(
            $var ?? self::DEFAULT_DIRECTORY_FOR_VAR
        );

        $this->var_cache = $var_cache = $this->ensure_trailing_separator(
            $var_cache ?? $var . self::DEFAULT_DIRECTORY_FOR_CACHE
        );

        $this->var_cache_configs = $this->ensure_trailing_separator(
            $var_cache_configs ?? $var_cache . self::DEFAULT_DIRECTORY_FOR_CACHE_CONFIGS
        );

        $this->var_files = $this->ensure_trailing_separator(
            $var_files ?? $var . self::DEFAULT_DIRECTORY_FOR_FILES
        );

        $this->var_tmp = $this->ensure_trailing_separator(
            $var_tmp ?? $var . self::DEFAULT_DIRECTORY_FOR_TMP
        );

        $this->var_lib = $this->ensure_trailing_separator(
            $var_lib ?? $var . self::DEFAULT_DIRECTORY_FOR_LIB
        );
    }

    /**
     * @param non-empty-string $path
     *
     * @return non-empty-string
     */
    private function ensure_trailing_separator(string $path): string
    {
        return rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}
