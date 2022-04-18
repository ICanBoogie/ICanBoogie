<?php

namespace ICanBoogie;

use ICanBoogie\Application\ClearCacheEvent;

return [

	ClearCacheEvent::qualify(Application::class) => [ Hooks::class, 'on_clear_cache' ],

];
