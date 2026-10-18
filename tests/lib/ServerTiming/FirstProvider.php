<?php

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\HTTP\Headers\ServerTiming;
use ICanBoogie\ServerTimingProvider;

final class FirstProvider implements ServerTimingProvider
{
    public function alter(ServerTiming $timing): void
    {
        $timing->add('first');
    }
}
