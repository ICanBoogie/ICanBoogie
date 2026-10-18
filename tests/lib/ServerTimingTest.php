<?php

namespace Test\ICanBoogie;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function json_decode;
use function shell_exec;

use const PHP_BINARY;

/**
 * The application is a singleton booted by the test bootstrap, so each scenario runs in its own PHP
 * process. See `ServerTiming/config_a/app.php` for the scenarios.
 */
final class ServerTimingTest extends TestCase
{
    public function test_disabled_resolves_no_provider(): void
    {
        // The scenario adds a provider that throws, if it was resolved the run would fail.
        $actual = $this->run_scenario('disabled');

        $this->assertSame([ 'server_timing' => null ], $actual);
    }

    public function test_enabled_without_providers(): void
    {
        $actual = $this->run_scenario('no_providers');

        $this->assertSame([ 'server_timing' => null ], $actual);
    }

    public function test_enabled_with_default_providers(): void
    {
        $actual = $this->run_scenario('enabled');

        $this->assertIsString($actual['server_timing']);
        $this->assertMatchesRegularExpression(
            '/^app;dur=[\d.]+, config;dur=[\d.]+;desc="\d+ configs"$/',
            $actual['server_timing']
        );
    }

    public function test_provider_can_be_removed(): void
    {
        $actual = $this->run_scenario('without_app');

        $this->assertIsString($actual['server_timing']);
        $this->assertMatchesRegularExpression('/^config;dur=[\d.]+;desc="\d+ configs"$/', $actual['server_timing']);
    }

    public function test_metrics_of_the_response_are_kept(): void
    {
        $actual = $this->run_scenario('preexisting');

        $this->assertIsString($actual['server_timing']);
        $this->assertStringStartsWith('responder;dur=12.5, app;dur=', $actual['server_timing']);
    }

    public function test_providers_are_called_in_the_order_they_were_added_once(): void
    {
        $actual = $this->run_scenario('ordered');

        $this->assertSame([ 'server_timing' => 'second, first' ], $actual);
    }

    public function test_provider_removed_by_a_later_fragment_is_not_called(): void
    {
        $actual = $this->run_scenario('removed_later');

        $this->assertSame([ 'server_timing' => 'second' ], $actual);
    }

    public function test_provider_exception_propagates(): void
    {
        $actual = $this->run_scenario('failing');

        $this->assertSame([ 'error' => 'RuntimeException', 'message' => 'Provider failed.' ], $actual);
    }

    /**
     * @return array<mixed>
     */
    private function run_scenario(string $scenario): array
    {
        $output = shell_exec(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ServerTiming/run.php') . ' ' . escapeshellarg($scenario)
        );

        $this->assertIsString($output);

        $result = json_decode($output, true);

        $this->assertIsArray($result, $output);

        return $result;
    }
}
