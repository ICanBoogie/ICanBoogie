<?php

return array
(
	'reportAddress' => null,
	'verbose' => true,
	'lineNumber' => true,
	'stackTrace' => true,
	'codeSample' => true,

	'mode' => 'test',
	'modes' => array
	(
		'test' => array
		(
			'verbose' => true
		),

		'production' => array
		(
			'verbose' => false,
			'stackTrace' => false,
			'codeSample' => false
		)
	)
);