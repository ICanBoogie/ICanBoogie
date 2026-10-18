<?php

namespace Test\ICanBoogie\ServerTiming;

use ICanBoogie\AppConfigBuilder;
use ICanBoogie\AppServerTimingProvider;
use ICanBoogie\ConfigServerTimingProvider;

return function (AppConfigBuilder $config) {
    switch (getenv('SERVER_TIMING_SCENARIO')) {
        case 'disabled':
            $config->add_server_timing_provider(FailingProvider::class);
            break;

        case 'enabled':
        case 'preexisting':
            $config->enable_server_timing();
            break;

        case 'without_app':
            $config
                ->enable_server_timing()
                ->remove_server_timing_provider(AppServerTimingProvider::class);
            break;

        case 'no_providers':
            $config
                ->enable_server_timing()
                ->remove_server_timing_provider(AppServerTimingProvider::class)
                ->remove_server_timing_provider(ConfigServerTimingProvider::class);
            break;

        case 'ordered':
            $config
                ->enable_server_timing()
                ->remove_server_timing_provider(AppServerTimingProvider::class)
                ->remove_server_timing_provider(ConfigServerTimingProvider::class)
                ->add_server_timing_provider(SecondProvider::class)
                ->add_server_timing_provider(FirstProvider::class)
                ->add_server_timing_provider(SecondProvider::class);
            break;

        case 'removed_later':
            $config
                ->enable_server_timing()
                ->remove_server_timing_provider(AppServerTimingProvider::class)
                ->remove_server_timing_provider(ConfigServerTimingProvider::class)
                ->add_server_timing_provider(FirstProvider::class)
                ->add_server_timing_provider(SecondProvider::class);
            break;

        case 'failing':
            $config
                ->enable_server_timing()
                ->add_server_timing_provider(FailingProvider::class);
            break;
    }

    return $config;
};
