<?php

namespace Test\ICanBoogie;

use ICanBoogie\AppConfigBuilder;
use ICanBoogie\AppServerTimingProvider;
use ICanBoogie\ConfigServerTimingProvider;
use PHPUnit\Framework\TestCase;

final class AppConfigBuilderTest extends TestCase
{
    public function test_server_timing_is_disabled_by_default(): void
    {
        $actual = new AppConfigBuilder()->build();

        $this->assertFalse($actual->server_timing);
        $this->assertSame([], $actual->server_timing_providers);
    }

    public function test_enable_then_disable_server_timing(): void
    {
        $actual = new AppConfigBuilder()
            ->enable_server_timing()
            ->disable_server_timing()
            ->build();

        $this->assertFalse($actual->server_timing);
    }

    public function test_enable_server_timing(): void
    {
        $actual = new AppConfigBuilder()->enable_server_timing()->build();

        $this->assertTrue($actual->server_timing);
    }

    public function test_adding_a_provider_does_not_enable_server_timing(): void
    {
        $actual = new AppConfigBuilder()
            ->add_server_timing_provider(AppServerTimingProvider::class)
            ->build();

        $this->assertFalse($actual->server_timing);
    }

    public function test_adding_a_provider_twice_is_a_noop(): void
    {
        $actual = new AppConfigBuilder()
            ->add_server_timing_provider(ConfigServerTimingProvider::class)
            ->add_server_timing_provider(AppServerTimingProvider::class)
            ->add_server_timing_provider(ConfigServerTimingProvider::class)
            ->build();

        $this->assertSame(
            [ ConfigServerTimingProvider::class, AppServerTimingProvider::class ],
            $actual->server_timing_providers
        );
    }

    public function test_remove_provider(): void
    {
        $actual = new AppConfigBuilder()
            ->add_server_timing_provider(AppServerTimingProvider::class)
            ->add_server_timing_provider(ConfigServerTimingProvider::class)
            ->remove_server_timing_provider(AppServerTimingProvider::class)
            ->build();

        $this->assertSame([ ConfigServerTimingProvider::class ], $actual->server_timing_providers);
    }

    public function test_removing_a_provider_that_was_never_added_is_a_noop(): void
    {
        $actual = new AppConfigBuilder()
            ->remove_server_timing_provider(AppServerTimingProvider::class)
            ->build();

        $this->assertSame([], $actual->server_timing_providers);
    }
}
