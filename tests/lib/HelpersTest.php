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

use ICanBoogie\Autoconfig\Autoconfig;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function test_generate_token(): void
    {
        for ($i = 1; $i < 16; $i++) {
            $length = pow(2, $i);
            $token = generate_token($length, TOKEN_ALPHA);
            $this->assertEquals($length, strlen($token));
        }
    }

    public function test_generate_token_wide(): void
    {
        $token = generate_token_wide();

        $this->assertEquals(64, strlen($token));
    }

    /**
     * @dataProvider provide_test_resolve_app_paths
     *
     * @param string[] $expected
     */
    public function test_resolve_app_paths(string $root, string $server_name, array $expected): void
    {
        $this->assertEquals($expected, resolve_app_paths($root, $server_name));
    }

    /**
     * @return array<array{ string, string, string[] }>
     */
    public function provide_test_resolve_app_paths(): array
    {
        $root = __DIR__ . DIRECTORY_SEPARATOR . 'cases' . DIRECTORY_SEPARATOR . 'resolve_app_paths' . DIRECTORY_SEPARATOR;
        $root0 = $root . 'app_0' . DIRECTORY_SEPARATOR;
        $root1 = $root . 'app_1' . DIRECTORY_SEPARATOR;

        return [

            [ $root0, 'www.icanboogie.org',       [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'org' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'icanboogie.org',           [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'org' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'icanboogie.localhost',     [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'localhost' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'www.icanboogie.localhost', [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'localhost' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'icanboogie.fr',            [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'icanboogie.fr' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'www.icanboogie.fr',        [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'icanboogie.fr' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'cli',                      [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'cli' . DIRECTORY_SEPARATOR ] ],
            [ $root0, 'undefined',                [ $root0 . 'all' . DIRECTORY_SEPARATOR, $root0 . 'default' . DIRECTORY_SEPARATOR ] ],

            [ $root1, 'www.icanboogie.org',       [ $root1 . 'org' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'icanboogie.org',           [ $root1 . 'org' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'icanboogie.localhost',     [ $root1 . 'localhost' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'www.icanboogie.localhost', [ $root1 . 'localhost' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'icanboogie.fr',            [ $root1 . 'icanboogie.fr' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'www.icanboogie.fr',        [ $root1 . 'icanboogie.fr' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'cli',                      [ $root1 . 'cli' . DIRECTORY_SEPARATOR ] ],
            [ $root1, 'undefined',                [ ] ],

        ];
    }

    public function test_get_autoconfig(): void
    {
        $cwd = getcwd();
        assert(is_string($cwd));
        $package_root = dirname($cwd);

        $this->assertEquals(new Autoconfig(
            base_path: "$cwd/",
            app_path: "$cwd/app/",
            app_paths: [

                "$cwd/app/all/",
                "$cwd/app/default/",

            ],
            config_paths: [

                "$package_root/config" => Autoconfig::CONFIG_WEIGHT_APP,
                "$package_root/vendor/icanboogie/bind-http/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
                "$package_root/vendor/icanboogie/bind-event/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
                "$package_root/vendor/icanboogie/bind-routing/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
                "$cwd/app/all/config" => Autoconfig::CONFIG_WEIGHT_APP,
                "$cwd/app/default/config" => Autoconfig::CONFIG_WEIGHT_APP,
                "$package_root/vendor/icanboogie/bind-symfony-dependency-injection/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
                "$package_root/vendor/icanboogie/bind-prototype/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
                "$package_root/vendor/icanboogie/console/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,

            ],
            config_builders: [

                'ICanBoogie\AppConfig' => 'ICanBoogie\AppConfigBuilder',
                'ICanBoogie\Event\Config' => 'ICanBoogie\Binding\Event\ConfigBuilder',
                'ICanBoogie\Prototype\Config' => 'ICanBoogie\Binding\Prototype\ConfigBuilder',
                'ICanBoogie\Routing\RouteProvider' => 'ICanBoogie\Binding\Routing\ConfigBuilder',
                'ICanBoogie\Binding\SymfonyDependencyInjection\Config' => 'ICanBoogie\Binding\SymfonyDependencyInjection\ConfigBuilder',
                'ICanBoogie\Debug\Config' => 'ICanBoogie\Debug\ConfigBuilder',

            ],
            locale_paths: [

            ],
            filters: [

            ],
        ), get_autoconfig());
    }
}
