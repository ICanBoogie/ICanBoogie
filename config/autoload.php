<?php

$lib = $path . 'lib' . DIRECTORY_SEPARATOR;
$core = $lib . 'core' . DIRECTORY_SEPARATOR;
$operation = $lib . 'operation' . DIRECTORY_SEPARATOR;
$toolkit = $lib . 'toolkit' . DIRECTORY_SEPARATOR;

return array
(
	'ICanBoogie\Configs' => $core . 'accessors/configs.php',
	'ICanBoogie\Core' => $core . 'core.php',
	'ICanBoogie\Debug' => $core . 'debug.php',
	'ICanBoogie\Exception' => $core . 'exception.php',
	'ICanBoogie\Models' => $core . 'accessors/models.php',
	'ICanBoogie\Module' => $core . 'module.php',
	'ICanBoogie\Modules' => $core . 'accessors/modules.php',
	'ICanBoogie\Session' => $core . 'session.php',
	'ICanBoogie\Vars' => $core . 'accessors/vars.php',

	'ICanBoogie\Errors' => $toolkit . 'errors.php',
	'ICanBoogie\FileCache' => $toolkit . 'filecache.php',
	'ICanBoogie\Hook' => $toolkit . 'hook.php',
	'ICanBoogie\Mailer' => $toolkit . 'mailer.php',
	'ICanBoogie\Uploaded' => $toolkit . 'uploaded.php',

	'ICanBoogie\Operation' => $operation . 'operation.php',
	'ICanBoogie\Operation\BeforeProcessEvent' => $operation . 'operation.php',
	'ICanBoogie\Operation\FailureEvent' => $operation . 'operation.php',
	'ICanBoogie\Operation\GetFormEvent' => $operation . 'operation.php',
	'ICanBoogie\Operation\ProcessEvent' => $operation . 'operation.php',
	'ICanBoogie\Operation\FormHasExpired' => $operation . 'operation.php',
	'ICanBoogie\Operation\Response' => $operation . 'response.php',

	'ICanBoogie\AlohaOperation' => $operation . 'core/aloha.php',
	'ICanBoogie\PingOperation' => $operation . 'core/ping.php',
	'ICanBoogie\DeleteOperation' => $operation . 'activerecord/delete.php',
	'ICanBoogie\SaveOperation' => $operation . 'activerecord/save.php'
);