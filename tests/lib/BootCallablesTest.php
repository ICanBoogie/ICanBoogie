<?php

namespace Test\ICanBoogie;

use PHPUnit\Framework\TestCase;
use Test\ICanBoogie\Boot\BootCallables;

use function array_map;
use function escapeshellarg;
use function implode;
use function json_decode;
use function shell_exec;

use const PHP_BINARY;

/**
 * The application is a singleton booted by the test bootstrap, so each scenario runs in its own PHP
 * process.
 */
final class BootCallablesTest extends TestCase
{
    public function test_callables_run_in_order_before_boot_event(): void
    {
        $result = $this->boot([
            BootCallables::class . '::first',
            BootCallables::class . '::second',
        ]);

        $this->assertSame([ 'log' => [ 'first:booting', 'second', 'event' ] ], $result);
    }

    public function test_no_callables(): void
    {
        $this->assertSame([ 'log' => [ 'event' ] ], $this->boot([]));
    }

    /**
     * @param list<string> $callables
     *
     * @return array<mixed>
     */
    private function boot(array $callables): array
    {
        $args = implode(' ', array_map(escapeshellarg(...), $callables));
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/Boot/run.php') . " $args");

        $this->assertIsString($output);

        $result = json_decode($output, true);

        $this->assertIsArray($result, $output);

        return $result;
    }
}
