<?php

return array
(
	'report_address' => null,
	'verbose' => true,
	'line_number' => true,
	'stack_trace' => true,
	'code_sample' => true,

	'mode' => 'dev',
	'modes' => array
	(
		'dev' => array
		(
			'verbose' => true,
			'report' => false,
			'line_number' => true,
			'stack_trace' => true,
			'code_sample' => true
		),

		'test' => array
		(
			'verbose' => true,
			'report' => true,
			'line_number' => false,
			'stack_trace' => true,
			'code_sample' => false
		),

		'production' => array
		(
			'verbose' => false,
			'report' => true,
			'line_number' => false,
			'stack_trace' => false,
			'code_sample' => false
		)
	)
);