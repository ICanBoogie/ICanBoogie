<?php

namespace ICanBoogie\Service;

use Closure;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

use function ICanBoogie\app;

final class ServiceProviderTest extends TestCase
{
    public function test_service_provider_is_defined(): void
    {
        $this->assertInstanceOf(Closure::class, ServiceProvider::defined());
    }

    public function test_failure_on_undefined_service(): void
    {
        $this->expectException(ServiceNotFoundException::class);

        ref('madonna')->resolve();
    }

    /**
     * Tests service references are working.
     */
    public function test_ref(): void
    {
        $actual = ref('test.app')->resolve();

        $this->assertSame(app(), $actual);
    }
}
