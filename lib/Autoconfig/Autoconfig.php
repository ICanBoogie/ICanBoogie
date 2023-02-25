<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie\Autoconfig;

use ICanBoogie\Config\Builder;

use function array_merge;

final class Autoconfig
{
    public const ARG_BASE_PATH = 'base_path';
    public const ARG_APP_PATH = 'app_path';
    public const ARG_APP_PATHS = 'app_paths';
    public const ARG_CONFIG_PATHS = 'config_paths';
    public const ARG_CONFIG_BUILDERS = 'config_builders';
    public const ARG_LOCALE_PATHS = 'locale_paths';
    public const ARG_FILTERS = 'filters';

    public const CONFIG_WEIGHT_FRAMEWORK = -100;
    public const CONFIG_WEIGHT_MODULE = 0;
    public const CONFIG_WEIGHT_APP = 100;

    public const DEFAULT_APP_DIRECTORY = 'app';

    /**
     * @param array<string> $app_paths
     *     Where _value_ is a directory to scan for the app.
     * @param array<string, int> $config_paths
     *     Where _key_ is a path and _value_ a weight.
     * @param array<class-string, class-string<Builder<object>>> $config_builders
     *     Where _key_ is a config class and _value_ a config builder class.
     * @param array<string> $locale_paths
     *     Where _value_ is a locale directory.
     * @param array<callable-string> $filters
     *     Where _value_ is a callable to amend the Autoconfig.
     */
    public function __construct(
        public readonly string $base_path,
        public readonly string $app_path,
        public readonly array $app_paths,
        public readonly array $config_paths,
        public readonly array $config_builders,
        public readonly array $locale_paths,
        public readonly array $filters,
    ) {
    }

    /**
     * @param array{
     *     base_path?: string,
     *     app_path?: string,
     *     app_paths?: array<string>,
     *     config_paths?: array<string, int>,
     *     config_builders?: array<class-string, class-string<Builder<object>>>,
     *     locale_paths?: array<string>
     * } $changes
     */
    public function with(array $changes): self
    {
        return new self(...array_merge([
            'base_path' => $this->base_path,
            'app_path' => $this->app_path,
            'app_paths' => $this->app_paths,
            'config_paths' => $this->config_paths,
            'config_builders' => $this->config_builders,
            'locale_paths' => $this->locale_paths,
            'filters' => $this->filters,
        ], $changes));
    }
}
