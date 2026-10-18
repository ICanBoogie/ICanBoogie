<?php

namespace Test\ICanBoogie;

use ICanBoogie\AppConfig;
use ICanBoogie\AppConfigBuilder;
use ICanBoogie\AppServerTimingProvider;
use ICanBoogie\ConfigProfiler;
use ICanBoogie\ConfigProfiler\Record;
use ICanBoogie\ConfigServerTimingProvider;
use ICanBoogie\HTTP\Headers\ServerTiming;
use ICanBoogie\ServerTimingProvider\Intervals;
use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function json_decode;
use function microtime;
use function shell_exec;

use const PHP_BINARY;

final class ServerTimingProvidersTest extends TestCase
{
    /**
     * @var mixed
     */
    private mixed $request_time_float;

    /**
     * @var Record[]
     */
    private array $records;

    protected function setUp(): void
    {
        $this->request_time_float = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        $this->records = ConfigProfiler::$records ?? [];
    }

    protected function tearDown(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = $this->request_time_float;
        ConfigProfiler::$records = $this->records;
    }

    public function test_intervals_ignore_nothing(): void
    {
        $actual = Intervals::union_length([]);

        $this->assertSame(0.0, $actual);
    }

    public function test_intervals_count_nested_work_once(): void
    {
        // The inner work is recorded first, because records are added when the work ends.
        $actual = Intervals::union_length([ [ 2.0, 1.0 ], [ 0.0, 10.0 ] ]);

        $this->assertEqualsWithDelta(10.0, $actual, 1e-9);
    }

    public function test_intervals_merge_overlapping_work(): void
    {
        $actual = Intervals::union_length([ [ 0.0, 4.0 ], [ 3.0, 4.0 ], [ 10.0, 1.0 ] ]);

        $this->assertEqualsWithDelta(8.0, $actual, 1e-9);
    }

    public function test_app_adds_the_time_since_the_start_of_the_request(): void
    {
        $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 0.5;
        $timing = new ServerTiming();

        (new AppServerTimingProvider())->alter($timing);

        $this->assertCount(1, $timing->metrics);
        $metric = $timing->metrics[0];
        $this->assertSame('app', $metric->name);
        $this->assertNull($metric->description);
        $this->assertNotNull($metric->duration);
        $this->assertGreaterThanOrEqual(500.0, $metric->duration);
        $this->assertLessThan(5000.0, $metric->duration);
    }

    public function test_app_adds_nothing_without_request_time(): void
    {
        unset($_SERVER['REQUEST_TIME_FLOAT']);
        $timing = new ServerTiming();

        (new AppServerTimingProvider())->alter($timing);

        $this->assertSame([], $timing->metrics);
    }

    public function test_config_converts_seconds_to_milliseconds_without_truncating(): void
    {
        ConfigProfiler::$records = [ new Record(1000.0, 0.0123, AppConfig::class, AppConfigBuilder::class) ];
        $timing = new ServerTiming();

        (new ConfigServerTimingProvider())->alter($timing);

        $this->assertCount(1, $timing->metrics);
        $metric = $timing->metrics[0];
        $this->assertSame('config', $metric->name);
        $this->assertNotNull($metric->duration);
        $this->assertEqualsWithDelta(12.3, $metric->duration, 1e-9);
        $this->assertSame('1 configs', $metric->description);
    }

    public function test_config_counts_nested_work_once(): void
    {
        ConfigProfiler::$records = [
            new Record(1000.002, 0.001, AppConfig::class, AppConfigBuilder::class),
            new Record(1000.000, 0.010, AppConfig::class, AppConfigBuilder::class),
        ];
        $timing = new ServerTiming();

        (new ConfigServerTimingProvider())->alter($timing);

        $metric = $timing->metrics[0];
        $this->assertNotNull($metric->duration);
        $this->assertEqualsWithDelta(10.0, $metric->duration, 1e-6);
        $this->assertSame('2 configs', $metric->description);
    }

    public function test_config_adds_nothing_without_records(): void
    {
        ConfigProfiler::$records = [];
        $timing = new ServerTiming();

        (new ConfigServerTimingProvider())->alter($timing);

        $this->assertSame([], $timing->metrics);
    }

    public function test_config_tolerates_uninitialized_records(): void
    {
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/ServerTiming/uninitialized.php'));

        $this->assertIsString($output);

        $actual = json_decode($output, true);

        $this->assertSame('', $actual, $output);
    }
}
