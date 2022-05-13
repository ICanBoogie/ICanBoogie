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

final class AppConfigBuilder implements Builder
{
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

    private bool $cache_modules = false;

    public function enable_module_caching(): self
    {
        $this->cache_modules = true;

        return $this;
    }

    public function disable_module_caching(): self
    {
        $this->cache_modules = false;

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

    private ?string $repository = null;

    public function set_repository(string $value): self
    {
        $this->repository = $value;

        return $this;
    }

    private ?string $repository_cache = null;

    public function set_repository_cache(string $value): self
    {
        $this->repository_cache = $value;

        return $this;
    }

    private ?string $repository_cache_configs = null;

    public function set_repository_cache_configs(string $value): self
    {
        $this->repository_cache_configs = $value;

        return $this;
    }

    private ?string $repository_files = null;

    public function set_repository_files(string $value): self
    {
        $this->repository_files = $value;

        return $this;
    }

    private ?string $repository_tmp = null;

    public function set_repository_tmp(string $value): self
    {
        $this->repository_tmp = $value;

        return $this;
    }

    private ?string $repository_vars = null;

    public function set_repository_vars(string $value): self
    {
        $this->repository_vars = $value;

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
            cache_modules: $this->cache_modules,
            storage_for_config: $this->storage_for_config,
            storage_for_vars: $this->storage_for_vars,
            error_handler: $this->error_handler,
            exception_handler: $this->exception_handler,
            repository: $this->repository,
            repository_cache: $this->repository_cache,
            repository_cache_configs: $this->repository_cache_configs,
            repository_files: $this->repository_files,
            repository_tmp: $this->repository_tmp,
            repository_var: $this->repository_vars,
            session: $this->session,
        );
    }
}
