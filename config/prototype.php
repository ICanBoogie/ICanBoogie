<?php

namespace ICanBoogie;

return [

	Application::class . '::get_logger' => Logger::class . '::get_logger',
	Application::class . '::get_session' => SessionWithEvent::class . '::for_app'

];
