<?php

namespace ICanBoogie\Responder;

use ICanBoogie\Binding\Routing\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
    ->get('/api/ping', 'api:ping');
