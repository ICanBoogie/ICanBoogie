<?php

$ar = $path . 'lib/activerecord/';
$core = $path . 'lib/core/';
$i18n = $path . 'lib/i18n/';
$operation = $path . 'lib/operation/';
$toolkit = $path . 'lib/toolkit/';

return array
(
	'autoload' => array
	(
		'ICanBoogie\Accessor\Configs' => $core . 'accessor/configs.php',
		'ICanBoogie\Accessor\Connections' => $core . 'accessor/connections.php',
		'ICanBoogie\Accessor\Models' => $core . 'accessor/models.php',
		'ICanBoogie\Accessor\Modules' => $core . 'accessor/modules.php',
		'ICanBoogie\Accessor\Vars' => $core . 'accessor/vars.php',
		'ICanBoogie\Database' => $core . 'database.php',
		'ICanBoogie\DatabaseTable' => $core . 'databasetable.php',
		'ICanBoogie\Event' => $core . 'event.php',
		'ICanBoogie\Exception' => $core . 'exception.php',
		'ICanBoogie\Exception\HTTP' => $core . 'exception.php',
		'ICanBoogie\Module' => $core . 'module.php',
		'ICanBoogie\Object' => $core . 'object.php',
		'ICanBoogie\Route' => $core . 'route.php',
		'ICanBoogie\Session' => $core . 'session.php',

		'ICanBoogie\ActiveRecord' => $ar . 'activerecord.php',
		'ICanBoogie\ActiveRecord\Model' => $ar . '/model.php',
		'ICanBoogie\ActiveRecord\Query' => $ar . '/query.php',

		'WdArray' => $toolkit . 'array.php',

		'ICanBoogie\I18n' => $i18n . 'i18n.php',
		'ICanBoogie\I18n\Locale' => $i18n . 'locale.php',
		'ICanBoogie\I18n\Formatter\Date' => $i18n . 'formatter/date.php',
		'ICanBoogie\I18n\Formatter\Number' => $i18n . 'formatter/number.php',
		'ICanBoogie\I18n\Translator' => $i18n . 'translator.php',
		'ICanBoogie\I18n\Translator\Proxi' => $i18n . 'proxi.php',

		'ICanBoogie\Debug' => $toolkit . 'debug.php',
		'ICanBoogie\FileCache' => $toolkit . 'filecache.php',
		'ICanBoogie\Hook' => $toolkit . 'hook.php',
		'ICanBoogie\Image' => $toolkit . 'image.php',
		'ICanBoogie\Mailer' => $toolkit . 'mailer.php',
		'ICanBoogie\Security' => $toolkit . 'security.php',
		'ICanBoogie\Uploaded' => $toolkit . 'uploaded.php',

		'ICanBoogie\Operation' => $operation . 'operation.php',
		'ICanBoogie\Operation\ActiveRecord\Delete' => $operation . 'activerecord/delete.php',
		'ICanBoogie\Operation\ActiveRecord\Save' => $operation . 'activerecord/save.php',
		'ICanBoogie\Operation\Core\Aloha' => $operation . 'core/aloha.php',
		'ICanBoogie\Operation\Core\Ping' => $operation . 'core/ping.php'
	),

	'cache configs' => false,
	'cache modules' => false,
	'cache catalogs' => false,

	'classes aliases' => array
	(

	),

	'config constructors' => array
	(
		'debug' => array('ICanBoogie\Debug::synthesize_config'),
		'methods' => array('ICanBoogie\Object::synthesize_methods', 'hooks')
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
	'repository.vars' => DIRECTORY_SEPARATOR . 'repository' . DIRECTORY_SEPARATOR . 'lib',

	'session' => array
	(
		'name' => 'ICanBoogie'
	)
);