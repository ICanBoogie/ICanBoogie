<?php

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\AppConfigBuilder;

return function (AppConfigBuilder $config) {
    if (getenv('SERVER_TIMING_SCENARIO') === 'removed_later') {
        $config->remove_server_timing_provider(FirstProvider::class);
    }

    return $config;
};
