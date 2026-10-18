<?php

namespace ICanBoogie;

use ICanBoogie\HTTP\Headers\ServerTiming;

use function is_float;
use function microtime;

/**
 * Adds the `app` metric, the time elapsed since the start of the request.
 */
final class AppServerTimingProvider implements ServerTimingProvider
{
    public function alter(ServerTiming $timing): void
    {
        $started_at = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;

        if (!is_float($started_at)) {
            return;
        }

        $timing->add('app', (microtime(true) - $started_at) * 1000);
    }
}
