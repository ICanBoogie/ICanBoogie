<?php

namespace ICanBoogie;

use ICanBoogie\Application\ClearCacheEvent;

return [

	ClearCacheEvent::for(Application::class) => [ Hooks::class, 'on_clear_cache' ],

];
