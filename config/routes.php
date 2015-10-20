<?php

namespace ICanBoogie\Routing;

return [

	'api:ping' => [

		RouteDefinition::PATTERN => '/api/ping',
		RouteDefinition::CONTROLLER => PingController::class

	]

];
