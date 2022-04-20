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

use ICanBoogie\Routing\RouteProvider;
use PHPUnit\Framework\TestCase;

class GettersTest extends TestCase
{
    /**
     * @dataProvider provide_getter
     */
    public function test_getter(string $id, string $expected): void
    {
        $this->assertInstanceOf($expected, app()->$id);
    }

    public function provide_getter(): array
    {
        return [

            [ 'routes', RouteProvider::class ],

        ];
    }
}
