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

use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\HTTP\Request;
use ICanBoogie\Storage\Storage;
use PHPUnit\Framework\TestCase;

final class ApplicationTest extends TestCase
{
    private static Application $app;

    public static function setUpBeforeClass(): void
    {
        self::$app = app();
    }

    public function test_only_one_instance(): void
    {
        $this->expectException(ApplicationAlreadyInstantiated::class);

        Application::new([]);
    }

    public function test_object_should_have_app_property(): void
    {
        $o = new Prototyped();
        $this->assertSame(self::$app, $o->app);
    }

    public function test_second_boot(): void
    {
        $this->expectException(ApplicationAlreadyBooted::class);

        self::$app->boot();
    }

    public function test_is_configured(): void
    {
        $this->assertTrue(self::$app->is_configured);
    }

    public function test_is_booting(): void
    {
        $this->assertFalse(self::$app->is_booting);
    }

    public function test_is_booted(): void
    {
        $this->assertTrue(self::$app->is_booted);
    }

    public function test_is_running(): void
    {
        $this->assertFalse(self::$app->is_running);
    }

    public function test_get_config(): void
    {
        $app = self::$app;
        $this->assertInstanceOf(AppConfig::class, $app->config);
    }

    /**
     * @dataProvider provide_test_property_type
     *
     * @param string $property Property of the application.
     * @param string $class The expected class of the property.
     */
    public function test_property_type(string $property, string $class): void
    {
        $this->assertInstanceOf($class, self::$app->$property);
    }

    public function provide_test_property_type(): array
    {
        return [

            [ 'vars',              Storage::class ],
            [ 'configs',           Config::class ],
            [ 'request',           Request::class ],
            [ 'events',            EventCollection::class ],
            [ 'timezone',          TimeZone::class ]

        ];
    }

    public function test_set_timezone(): void
    {
        self::$app->timezone = 'Europe/Madrid';
        $this->assertInstanceOf(TimeZone::class, self::$app->timezone);
        $this->assertEquals('Europe/Madrid', (string) self::$app->timezone);
    }

    public function test_clear_cache(): void
    {
        $invoked = false;

        $app = app();
        $app->events->once(function (ClearCacheEvent $event) use (&$invoked) {
            $invoked = true;
        });

        $app->clear_cache();

        $this->assertTrue($invoked);
    }
}
