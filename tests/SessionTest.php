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

class SessionTest extends \PHPUnit_Framework_TestCase
{
    public function test_exists()
    {
        $this->assertFalse(Session::exists());
    }

	public function test_get_session()
    {
        $this->assertInstanceOf(Session::class, Session::get_session(app()));
    }
}
