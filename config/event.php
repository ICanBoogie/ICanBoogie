<?php

namespace ICanBoogie;

$hooks = Hooks::class . '::';

return [

	Core::class . '::clear_cache' => $hooks . 'on_clear_cache'

];
