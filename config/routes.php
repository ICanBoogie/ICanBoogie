<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Binding\Routing\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
    ->route('/api/ping', 'api:ping');
