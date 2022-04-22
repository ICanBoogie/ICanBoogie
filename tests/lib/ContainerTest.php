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
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ContainerTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = app()->container->get('service_container');
    }

    /**
     * @dataProvider provide_parameter
     */
    public function test_parameter(string $parameter, mixed $expected): void
    {
        $this->assertEquals(
            $expected,
            $this->container->getParameter($parameter)
        );
    }

    public function provide_parameter(): array
    {
        return [

            [ 'routing.action_responder.aliases', [ 'api:ping' => 'ICanBoogie\Routing\PingController' ] ],

        ];
    }
}
