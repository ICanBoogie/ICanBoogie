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

use ICanBoogie\Config\Builder;

/**
 * @implements Builder<AppConfig>
 */
final class AppConfigBuilder implements Builder
{
    public static function get_fragment_filename(): string
    {
        return 'app';
    }

    private bool $cache_catalogs = false;

    public function enable_catalog_caching(): self
    {
        $this->cache_catalogs = true;

        return $this;
    }

    public function disable_catalog_caching(): self
    {
        $this->cache_catalogs = false;

        return $this;
    }

    private bool $cache_configs = false;

    public function enable_config_caching(): self
    {
        $this->cache_configs = true;

        return $this;
    }

    public function disable_config_caching(): self
    {
        $this->cache_configs = false;

        return $this;
    }

    /**
     * @var callable|null
     */
    private mixed $storage_for_config = null;

    public function set_storage_for_config(callable $value): self
    {
        $this->storage_for_config = $value;

        return $this;
    }

    /**
     * @var callable|null
     */
    private mixed $storage_for_vars = null;

    public function set_storage_for_vars(callable $value): self
    {
        $this->storage_for_vars = $value;

        return $this;
    }

    /**
     * @var callable|null
     */
    private mixed $error_handler = null;

    public function set_error_handler(callable $value): self
    {
        $this->error_handler = $value;

        return $this;
    }

    /**
     * @var callable|null
     */
    private mixed $exception_handler = null;

    public function set_exception_handler(callable $value): self
    {
        $this->exception_handler = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var(string $value): self
    {
        $this->var = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var_cache = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var_cache(string $value): self
    {
        $this->var_cache = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var_cache_configs = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var_cache_configs(string $value): self
    {
        $this->var_cache_configs = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var_files = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var_files(string $value): self
    {
        $this->var_files = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var_tmp = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var_tmp(string $value): self
    {
        $this->var_tmp = $value;

        return $this;
    }

    /**
     * @var non-empty-string|null
     */
    private ?string $var_lib = null;

    /**
     * @param non-empty-string $value
     *
     * @return $this
     */
    public function set_var_lib(string $value): self
    {
        $this->var_lib = $value;

        return $this;
    }

    /**
     * @phpstan-var array<SessionOptions::OPTION_*, mixed>
     */
    private array $session = [];

    /**
     * @phpstan-param array<SessionOptions::OPTION_*, mixed> $value
     *
     * @return $this
     */
    public function set_session(array $value): self
    {
        $this->session = $value;

        return $this;
    }

    public function build(): AppConfig
    {
        return new AppConfig(
            cache_catalogs: $this->cache_catalogs,
            cache_configs: $this->cache_configs,
            storage_for_config: $this->storage_for_config,
            storage_for_vars: $this->storage_for_vars,
            error_handler: $this->error_handler,
            exception_handler: $this->exception_handler,
            var: $this->var,
            var_cache: $this->var_cache,
            var_cache_configs: $this->var_cache_configs,
            var_files: $this->var_files,
            var_lib: $this->var_lib,
            var_tmp: $this->var_tmp,
            session: $this->session,
        );
    }
}
