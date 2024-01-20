<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Binding\Routing\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
    ->get('/api/ping', 'api:ping');
