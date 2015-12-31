<?php

namespace ICanBoogie;

$hooks = Hooks::class . '::';

return [

	Core::class . '::get_logger' => Logger::class . '::get_logger',
	Core::class . '::get_session' => SessionWithEvent::class . '::for_app'

];
