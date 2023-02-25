<?php

namespace ICanBoogie;

use ICanBoogie\Debug\ConfigBuilder;

return fn(ConfigBuilder $config) => null;

return [

	'code_sample' => true,
	'line_number' => true,
	'mode' => 'dev',
	'stack_trace' => true,
	'exception_chain' => true,
	'verbose' => true,

	'modes' => [

		'dev' => [

		],

		'stage' => [

			'code_sample' => false,
			'line_number' => false,
		],

		'production' => [

			'code_sample' => false,
			'line_number' => false,
			'stack_trace' => false,
			'exception_chain' => false,
			'verbose' => false
		]
	]
];
