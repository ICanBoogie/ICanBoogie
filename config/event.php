<?php

namespace ICanBoogie;

$hooks = Hooks::class . '::';

/**
 * @uses Hooks::on_clear_cache()
 */
return [

	Application::class . '::clear_cache' => $hooks . 'on_clear_cache'

];
