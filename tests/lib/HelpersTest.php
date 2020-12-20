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

class HelpersTest extends \PHPUnit\Framework\TestCase
{
	public function test_generate_token()
	{
		for ($i = 1 ; $i < 16 ; $i++)
		{
			$length = pow(2, $i);
			$token = generate_token($length, TOKEN_ALPHA);
			$this->assertEquals($length, strlen($token));
		}
	}

	public function test_generate_token_wide()
	{
		$token = generate_token_wide();

		$this->assertEquals(64, strlen($token));
	}

	/**
	 * @dataProvider provide_test_resolve_app_paths
	 */
	public function test_resolve_app_paths($root, $server_name, $expected)
	{
		$this->assertEquals($expected, resolve_app_paths($root, $server_name));
	}

	public function provide_test_resolve_app_paths()
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

	public function test_get_autoconfig()
	{
		$cwd = getcwd();
		$package_root = dirname($cwd);

		$this->assertSame([

			Autoconfig::BASE_PATH => $cwd,

			Autoconfig::APP_PATH => "$cwd/app",

			Autoconfig::APP_PATHS => [

				"$cwd/app/all/",
				"$cwd/app/default/",

			],


			Autoconfig::LOCALE_PATH => [

				"$package_root/locale"

			],
			Autoconfig::CONFIG_CONSTRUCTOR => [

				'app' => [ 'ICanBoogie\AppConfig::synthesize' ],
				'debug' => [ 'ICanBoogie\Debug::synthesize_config' ],
				'event' => [ 'ICanBoogie\Binding\Event\EventConfigSynthesizer::synthesize' ],
				'http_dispatchers' => [ 'ICanBoogie\Binding\HTTP\Hooks::synthesize_dispatchers_config', 'http' ],
				'prototype' => [ 'ICanBoogie\Binding\Prototype\PrototypeConfigSynthesizer::synthesize' ],
				'routes' => [ 'ICanBoogie\Binding\Routing\Hooks::synthesize_routes_config' ]

			],

			Autoconfig::AUTOCONFIG_FILTERS => [

				'ICanBoogie\Autoconfig\Hooks::filter_autoconfig'

			],

			Autoconfig::CONFIG_PATH => [

				"$package_root/config" => Autoconfig::CONFIG_WEIGHT_APP,
				"$package_root/vendor/icanboogie/bind-http/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
				"$package_root/vendor/icanboogie/bind-event/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
				"$package_root/vendor/icanboogie/bind-routing/config" => Autoconfig::CONFIG_WEIGHT_FRAMEWORK,
				"$cwd/app/all/config" => Autoconfig::CONFIG_WEIGHT_APP,
				"$cwd/app/default/config" => Autoconfig::CONFIG_WEIGHT_APP,

			],

		], get_autoconfig());
	}
}
