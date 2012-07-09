<?php

$lib = $path . 'lib' . DIRECTORY_SEPARATOR;
$ar = $lib . 'activerecord' . DIRECTORY_SEPARATOR;
$core = $lib . 'core' . DIRECTORY_SEPARATOR;
$http = $lib . 'http' . DIRECTORY_SEPARATOR;
$i18n = $lib . 'i18n' . DIRECTORY_SEPARATOR;
$operation = $lib . 'operation' . DIRECTORY_SEPARATOR;
$toolkit = $lib . 'toolkit' . DIRECTORY_SEPARATOR;

return array
(
	'autoload' => array
	(
		'ICanBoogie\Configs' => $core . 'accessors/configs.php',
		'ICanBoogie\Connections' => $core . 'accessors/connections.php',
		'ICanBoogie\Core' => $core . 'core.php',
		'ICanBoogie\Database' => $core . 'database.php',
		'ICanBoogie\Database\ConnectionException' => $core . 'database.php',
		'ICanBoogie\Database\ExecutionException' => $core . 'database.php',
		'ICanBoogie\Database\Statement' => $core . 'database.php',
		'ICanBoogie\DatabaseTable' => $core . 'databasetable.php',
		'ICanBoogie\Debug' => $core . 'debug.php',
		'ICanBoogie\Event' => $core . 'event.php',
		'ICanBoogie\Event\ObjectProperty' => $core . 'object.php',
		'ICanBoogie\Events' => $core . 'event.php',
		'ICanBoogie\Exception' => $core . 'exception.php',
		'ICanBoogie\Exception\HTTP' => $core . 'exception.php',
		'ICanBoogie\Models' => $core . 'accessors/models.php',
		'ICanBoogie\Module' => $core . 'module.php',
		'ICanBoogie\Modules' => $core . 'accessors/modules.php',
		'ICanBoogie\Object' => $core . 'object.php',
		'ICanBoogie\Route' => $core . 'route.php',
		'ICanBoogie\Routes' => $core . 'route.php',
		'ICanBoogie\Session' => $core . 'session.php',
		'ICanBoogie\Vars' => $core . 'accessors/vars.php',

		'ICanBoogie\ActiveRecord' => $ar . 'activerecord.php',
		'ICanBoogie\ActiveRecord\Model' => $ar . '/model.php',
		'ICanBoogie\ActiveRecord\Query' => $ar . '/query.php',

		'ICanBoogie\HTTP\Dispatcher' => $http . 'dispatcher.php',
		'ICanBoogie\HTTP\Headers' => $http . 'headers.php',
		'ICanBoogie\HTTP\Request' => $http . 'request.php',
		'ICanBoogie\HTTP\Response' => $http . 'response.php',
		'ICanBoogie\HTTP\UnsupportedMethodException' => $http . 'http.php',

		'ICanBoogie\I18n' => $i18n . 'i18n.php',
		'ICanBoogie\I18n\Locale' => $i18n . 'locale.php',
		'ICanBoogie\I18n\DateFormatter' => $i18n . 'formatter/date.php',
		'ICanBoogie\I18n\NumberFormatter' => $i18n . 'formatter/number.php',
		'ICanBoogie\I18n\Translator' => $i18n . 'translator.php',
		'ICanBoogie\I18n\Translator\Proxi' => $i18n . 'proxi.php',

		'ICanBoogie\Errors' => $toolkit . 'errors.php',
		'ICanBoogie\FileCache' => $toolkit . 'filecache.php',
		'ICanBoogie\Hook' => $toolkit . 'hook.php',
		'ICanBoogie\Image' => $toolkit . 'image.php',
		'ICanBoogie\Mailer' => $toolkit . 'mailer.php',
		'ICanBoogie\Security' => $toolkit . 'security.php',
		'ICanBoogie\Uploaded' => $toolkit . 'uploaded.php',

		'ICanBoogie\Operation' => $operation . 'operation.php',
		'ICanBoogie\Operation\BeforeProcessEvent' => $operation . 'operation.php',
		'ICanBoogie\Operation\FailureEvent' => $operation . 'operation.php',
		'ICanBoogie\Operation\GetFormEvent' => $operation . 'operation.php',
		'ICanBoogie\Operation\ProcessEvent' => $operation . 'operation.php',
		'ICanBoogie\Operation\ExpiredFormException' => $operation . 'operation.php',
		'ICanBoogie\Operation\Response' => $operation . 'response.php',
		
		'ICanBoogie\AlohaOperation' => $operation . 'core/aloha.php',
		'ICanBoogie\PingOperation' => $operation . 'core/ping.php',
		'ICanBoogie\DeleteOperation' => $operation . 'activerecord/delete.php',
		'ICanBoogie\SaveOperation' => $operation . 'activerecord/save.php'
	),

	'cache bootstrap' => false,
	'cache catalogs' => false,
	'cache configs' => false,
	'cache modules' => false,

	'classes aliases' => array
	(

	),

	'config constructors' => array
	(
		'debug' => array('ICanBoogie\Debug::synthesize_config'),
		'events' => array('ICanBoogie\Events::synthesize_config', 'hooks'),
		'prototypes' => array('ICanBoogie\Prototype::synthesize_config', 'hooks')
	),

	'connections' => array
	(

	),

	'modules' => array
	(

	),

	'repository' => '/repository',
	'repository.temp' => '/repository/tmp',
	'repository.cache' => '/repository/cache',
	'repository.files' => '/repository/files',

	'session' => array
	(
		'name' => 'ICanBoogie'
	)
);