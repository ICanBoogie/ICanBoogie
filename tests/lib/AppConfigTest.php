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

use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\SetStateHelper;

final class AppConfigTest extends TestCase
{
    public function test_default_repository(): void
    {
        $config = new AppConfig();

        $this->assertEquals([

            "repository/",
            "repository/cache/",
            "repository/cache/configs/",
            "repository/files/",
            "repository/tmp/",
            "repository/var/",

        ], $this->repository_to_array($config));
    }

    public function test_custom_repository(): void
    {
        $config = new AppConfig(repository: 'madonna');

        $this->assertEquals([

            "madonna/",
            "madonna/cache/",
            "madonna/cache/configs/",
            "madonna/files/",
            "madonna/tmp/",
            "madonna/var/",

        ], $this->repository_to_array($config));
    }

    public function test_custom_repository_cache(): void
    {
        $config = new AppConfig(repository_cache: 'madonna');

        $this->assertEquals([

            "repository/",
            "madonna/",
            "madonna/configs/",
            "repository/files/",
            "repository/tmp/",
            "repository/var/",

        ], $this->repository_to_array($config));
    }

    public function test_custom_repository_all(): void
    {
        $config = new AppConfig(
            repository: 'my-repository',
            repository_cache: 'my-cache',
            repository_cache_configs: 'my-configs',
            repository_files: 'my-files',
            repository_tmp: 'my-tmp',
            repository_var: 'my-var'
        );

        $this->assertEquals([

            "my-repository/",
            "my-cache/",
            "my-configs/",
            "my-files/",
            "my-tmp/",
            "my-var/",

        ], $this->repository_to_array($config));
    }

    public function test_export(): void
    {
        $config = new AppConfig(
            cache_catalogs: true,
            cache_configs: true,
            error_handler: [ Hooks::class, 'on_clear_cache' ],
        );

        $this->assertEquals($config, SetStateHelper::export_import($config));
    }

    /**
     * @return string[]
     */
    private function repository_to_array(AppConfig $config): array
    {
        return [

            $config->repository,
            $config->repository_cache,
            $config->repository_cache_configs,
            $config->repository_files,
            $config->repository_tmp,
            $config->repository_var,

        ];
    }
}
