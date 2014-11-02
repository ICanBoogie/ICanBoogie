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

class HelpersTest extends \PHPUnit_Framework_TestCase
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
		$root0 = $root . 'protected_0' . DIRECTORY_SEPARATOR;
		$root1 = $root . 'protected_1' . DIRECTORY_SEPARATOR;

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
}
