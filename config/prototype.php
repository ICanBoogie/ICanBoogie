<?php

namespace ICanBoogie;

/**
 * @uses Logger::get_logger()
 * @uses SessionWithEvent::for_app()
 */
return [

	Application::class . '::get_logger' => Logger::class . '::get_logger',
	Application::class . '::get_session' => SessionWithEvent::class . '::for_app'

];
