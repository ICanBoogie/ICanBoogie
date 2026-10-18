<?php

namespace ICanBoogie;

use ICanBoogie\Config\Builder;
use ICanBoogie\Storage\Storage;

use function array_filter;
use function array_values;
use function in_array;

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
     * @var (callable(Application): Storage<string, mixed>)|null
     */
    private mixed $storage_for_config = null;

    /**
     * @param callable(Application): Storage<string, mixed> $value
     */
    public function set_storage_for_config(callable $value): self
    {
        $this->storage_for_config = $value;

        return $this;
    }

    /**
     * @var (callable(Application): Storage<string, mixed>)|null
     */
    private mixed $storage_for_vars = null;

    /**
     * @param callable(Application): Storage<string, mixed> $value
     */
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

    private bool $server_timing = false;

    public function enable_server_timing(): self
    {
        $this->server_timing = true;

        return $this;
    }

    public function disable_server_timing(): self
    {
        $this->server_timing = false;

        return $this;
    }

    /**
     * @var list<class-string<ServerTimingProvider>>
     */
    private array $server_timing_providers = [];

    /**
     * Appends a provider, adding the same class twice is a no-op.
     *
     * Adding a provider does not enable the feature, see {@see enable_server_timing()}.
     *
     * @param class-string<ServerTimingProvider> $provider
     */
    public function add_server_timing_provider(string $provider): self
    {
        if (!in_array($provider, $this->server_timing_providers, true)) {
            $this->server_timing_providers[] = $provider;
        }

        return $this;
    }

    /**
     * Removes a provider, for instance one added by a package.
     *
     * @param class-string<ServerTimingProvider> $provider
     */
    public function remove_server_timing_provider(string $provider): self
    {
        $this->server_timing_providers = array_values(array_filter(
            $this->server_timing_providers,
            fn(string $other) => $other !== $provider
        ));

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
            server_timing: $this->server_timing,
            server_timing_providers: $this->server_timing_providers,
        );
    }
}
