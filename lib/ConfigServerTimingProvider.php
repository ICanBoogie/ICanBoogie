<?php

namespace ICanBoogie;

use ICanBoogie\HTTP\Headers\ServerTiming;
use ICanBoogie\ServerTimingProvider\Intervals;

use function array_map;
use function count;

/**
 * Adds the `config` metric, the time spent building configurations.
 */
final class ConfigServerTimingProvider implements ServerTimingProvider
{
    public function alter(ServerTiming $timing): void
    {
        // The records are not initialized until a config is built, which cached configs may never do.
        if (!isset(ConfigProfiler::$records) || !ConfigProfiler::$records) {
            return;
        }

        $records = ConfigProfiler::$records;
        $duration = Intervals::union_length(
            array_map(fn($record) => [ $record->timestamp, $record->duration ], $records)
        );

        $timing->add('config', $duration * 1000, count($records) . " configs");
    }
}
