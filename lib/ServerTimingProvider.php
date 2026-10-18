<?php

namespace ICanBoogie;

use ICanBoogie\HTTP\Headers\ServerTiming;

/**
 * Contributes metrics to the `Server-Timing` header of the response.
 *
 * Providers are services, they must be public. They are only resolved when the `server_timing`
 * option of {@see AppConfig} is enabled, and registered with
 * {@see AppConfigBuilder::add_server_timing_provider()}.
 */
interface ServerTimingProvider
{
    /**
     * Adds metrics to the timing of the response.
     */
    public function alter(ServerTiming $timing): void;
}
