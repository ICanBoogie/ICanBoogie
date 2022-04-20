<?php

namespace ICanBoogie\Routing;

use ICanBoogie\Binding\Routing\ConfigBuilder;

return function (ConfigBuilder $config) {

    $config->route('/api/ping', 'api:ping');

};
