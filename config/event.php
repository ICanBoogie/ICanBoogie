<?php

namespace ICanBoogie;

$hooks = Hooks::class . '::';

return [

	Application::class . '::clear_cache' => $hooks . 'on_clear_cache'

];
