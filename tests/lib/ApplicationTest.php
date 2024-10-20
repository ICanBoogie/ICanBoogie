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

use AssertionError;
use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\Application\InvalidState;
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
        $this->expectException(InvalidState::class);

        Application::new(new Autoconfig\Autoconfig(
            base_path: '',
            app_path: '',
            app_paths: [],
            config_paths: [],
            config_builders: [],
            locale_paths: [],
            filters: [],
        ));
    }

    public function test_second_boot(): void
    {
        $this->expectException(InvalidState::class);

        self::$app->boot();
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

    /**
     * @dataProvider provide_test_property_type
     *
     * @param string $property
     * @param class-string $class
     */
    public function test_property_type(string $property, string $class): void
    {
        $this->assertInstanceOf($class, self::$app->$property);
    }

    /**
     * @return array<array{ string, class-string }>
     */
    public static function provide_test_property_type(): array
    {
        return [

            [ 'vars',              Storage::class ],
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

        self::$app->events->once(function (ClearCacheEvent $event) use (&$invoked) {
            $invoked = true;
        });

        self::$app->clear_cache();

        $this->assertTrue($invoked);
    }

    public function test_service_for_class(): void
    {
        $actual = self::$app->service_for_class(Application::class);

        $this->assertSame(self::$app, $actual);
    }

    public function test_service_for_id(): void
    {
        $actual = self::$app->service_for_id('test.app', Application::class);

        $this->assertSame(self::$app, $actual);
    }

    public function test_service_for_id_fails_on_class_mismatch(): void
    {
        $this->expectException(AssertionError::class);
        $this->expectExceptionMessage("The service is not of the expected class");
        self::$app->service_for_id('test.app', ClearCacheEvent::class);
    }
}
