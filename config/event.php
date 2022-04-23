<?php

namespace ICanBoogie;

use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\Binding\Event\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
	->attach_to(Application::class, ClearCacheEvent::class, [ Hooks::class, 'on_clear_cache' ])
    ;
