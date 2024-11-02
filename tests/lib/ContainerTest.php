<?php

namespace ICanBoogie;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase
{
    #[DataProvider('provide_parameter')]
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

            [ 'routing.action_responder.aliases', [ 'api:ping' => \ICanBoogie\Responder\PingResponder::class ] ],

        ];
    }
}
