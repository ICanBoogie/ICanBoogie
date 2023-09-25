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
    public function test_default_var(): void
    {
        $config = new AppConfig();

        $this->assertEquals([

            "var/",
            "var/cache/",
            "var/cache/configs/",
            "var/files/",
            "var/lib/",
            "var/tmp/",

        ], $this->var_to_array($config));
    }

    public function test_custom_var(): void
    {
        $config = new AppConfig(var: 'madonna');

        $this->assertEquals([

            "madonna/",
            "madonna/cache/",
            "madonna/cache/configs/",
            "madonna/files/",
            "madonna/lib/",
            "madonna/tmp/",

        ], $this->var_to_array($config));
    }

    public function test_custom_var_cache(): void
    {
        $config = new AppConfig(var_cache: 'madonna');

        $this->assertEquals([

            "var/",
            "madonna/",
            "madonna/configs/",
            "var/files/",
            "var/lib/",
            "var/tmp/",

        ], $this->var_to_array($config));
    }

    public function test_custom_var_all(): void
    {
        $config = new AppConfig(
            var: 'my-var',
            var_cache: 'my-cache',
            var_cache_configs: 'my-configs',
            var_files: 'my-files',
            var_lib: 'my-lib',
            var_tmp: 'my-tmp',
        );

        $this->assertEquals([

            "my-var/",
            "my-cache/",
            "my-configs/",
            "my-files/",
            "my-lib/",
            "my-tmp/",

        ], $this->var_to_array($config));
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
    private function var_to_array(AppConfig $config): array
    {
        return [

            $config->var,
            $config->var_cache,
            $config->var_cache_configs,
            $config->var_files,
            $config->var_lib,
            $config->var_tmp,

        ];
    }
}
