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

final class ContainerTest extends TestCase
{
    /**
     * @dataProvider provide_parameter
     */
    public function test_parameter(string $parameter, mixed $expected): void
    {
        $this->assertEquals(
            $expected,
            app()->container->getParameter($parameter)
        );
    }

    /**
     * @return array<array{ string, array<string, class-string> }>
     */
    public static function provide_parameter(): array
    {
        return [

            [ 'routing.action_responder.aliases', [ 'api:ping' => 'ICanBoogie\Routing\PingController' ] ],

        ];
    }
}
