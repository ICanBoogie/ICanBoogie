<?php

namespace ICanBoogie;

use ICanBoogie\Binding\Prototype\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
    ->bind(Application::class, 'get_logger', [ Logger::class, 'for_app' ])
    ->bind(Application::class, 'get_session', [ SessionWithEvent::class, 'for_app' ]);
