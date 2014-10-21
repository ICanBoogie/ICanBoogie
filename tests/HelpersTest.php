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
}
