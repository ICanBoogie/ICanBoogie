<?php

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\HTTP\Headers\ServerTiming;
use ICanBoogie\ServerTimingProvider;
use RuntimeException;

final class FailingProvider implements ServerTimingProvider
{
    public function alter(ServerTiming $timing): void
    {
        throw new RuntimeException("Provider failed.");
    }
}
