<?php

namespace ICanBoogie;

use ICanBoogie\Application\ClearCacheEvent;

use function ICanBoogie\Event\qualify_type;

$hooks = Hooks::class . '::';

/**
 * @uses Hooks::on_clear_cache()
 */
return [

	qualify_type(Application::class, ClearCacheEvent::TYPE) => $hooks . 'on_clear_cache'

];
