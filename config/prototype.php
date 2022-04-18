<?php

namespace ICanBoogie;

return [

    Application::class . '::get_logger' => [ Logger::class, 'for_app' ],
    Application::class . '::get_session' => [ SessionWithEvent::class, 'for_app' ],

];
