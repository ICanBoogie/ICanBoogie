<?php

return array
(
	'code_sample' => true,
	'line_number' => true,
	'mode' => 'dev',
	'report' => false,
	'report_address' => null,
	'stack_trace' => true,
	'exception_chain' => true,
	'verbose' => true,

	'modes' => array
	(
		'dev' => array
		(
			'report' => false
		),

		'test' => array
		(
			'code_sample' => false,
			'line_number' => false,
			'report' => true
		),

		'production' => array
		(
			'code_sample' => false,
			'line_number' => false,
			'report' => true,
			'stack_trace' => false,
			'exception_chain' => false,
			'verbose' => false
		)
	)
);