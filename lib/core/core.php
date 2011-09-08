<?php

/*
 * This file is part of the ICanBoogie package.
 *
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ICanBoogie;

use ICanBoogie\I18n\Locale;

/**
 * @property \ICanBoogie\Accessor\Configs $configs Configurations accessor.
 * @property \ICanBoogie\Accessor\Connections $connections Database connections accessor.
 * @property \ICanBoogie\Accessor\Models $models Models accessor.
 * @property \ICanBoogie\Accessor\Modules $modules Modules accessor.
 * @property \ICanBoogie\Accessor\Vars $vars Persistant variables accessor.
 * @property \ICanBoogie\Database $db The primary database connection.
 * @property \ICanBoogie\Session $session User's session. Injected by the \ICanBoogie\Session class.
 * @property string $language Locale language.
 * @property string|int $timezeone Date and time timezone.
 * @property \ICanBoogie\I18n\Locale $locale Locale object matching the locale language.
 * @property array $config The "core" configuration.
 */
class Core extends Object
{
	static private $instance;

	/**
	 * Returns the unique instance of the core object.
	 *
	 * @param array $options
	 *
	 * @return Core The core object.
	 */
	public static function get_singleton(array $options=array())
	{
		if (self::$instance)
		{
			return self::$instance;
		}

		$class = get_called_class();

		return self::$instance = new $class($options);
	}

	/**
	 * @var boolean true if core is running, false otherwise.
	 */
	public static $is_running = false;

	/**
	 * Echos the exception and kills PHP.
	 *
	 * @param \Exception $exception
	 */
	public static function exception_handler(\Exception $exception)
	{
		exit($exception);
	}

	protected static $autoload = array();
	protected static $classes_aliases = array();

	/**
	 * Loads the file defining the specified class.
	 *
	 * The 'autoload' config property is used to define an array of 'class_name => file_path' pairs
	 * used to find the file required by the class.
	 *
	 * Class alias
	 * -----------
	 *
	 * Using the 'classes aliases' config property, one can specify aliases for classes. The
	 * 'classes aliases' config property is an array where the key is the alias name and the value
	 * the class name.
	 *
	 * When needed, a final class is created for the alias by extending the real class. The class
	 * is made final so that it cannot be subclassed.
	 *
	 * Class initializer
	 * -----------------
	 *
	 * If the loaded class defines the '__static_construct' method, the method is invoked to
	 * initialize the class.
	 *
	 * @param string $name The name of the class
	 *
	 * @return boolean Whether or not the required file could be found.
	 */
	private static function autoload_handler($name)
	{
		if ($name == 'parent')
		{
			return false;
		}

		$list = self::$autoload;

		if (isset($list[$name]))
		{
			require_once $list[$name];

			if (method_exists($name, '__static_construct'))
			{
				call_user_func(array($name, '__static_construct'));
			}

			return true;
		}

		$list = self::$classes_aliases;

		if (isset($list[$name]))
		{
			class_alias($list[$name], $name);

			return true;
		}

		return false;
	}

	/**
	 * Constructor.
	 *
	 * @param array $options Initial options to create the core object.
	 */
	protected function __construct(array $options=array())
	{
		$options = wd_array_merge_recursive
		(
			array
			(
				'paths' => array
				(
					'config' => array(ROOT),
					'locale' => array(ROOT)
				)
			),

			$options
		);

		$class = get_class($this);

		spl_autoload_register($class . '::autoload_handler');
		set_exception_handler($class . '::exception_handler');
		set_error_handler('ICanBoogie\Debug::error_handler');

		if (get_magic_quotes_gpc())
		{
			wd_kill_magic_quotes();
		}

		# the order is important, there's magic involved.

		$this->configs->add($options['paths']['config']);

		$config = wd_array_merge_recursive($options, $this->config);

		I18n::$load_paths = array_merge(I18n::$load_paths, $config['paths']['locale']);

		if ($config['cache configs'])
		{
			$this->configs->cache_syntheses = true;
			$this->configs->cache_repository = $config['repository.cache'] . '/core';
		}
	}

	/**
	 * Returns modules accessor.
	 *
	 * @return ModulesAccessor The modules accessor.
	 */
	protected function __get_modules()
	{
		$config = $this->config;

		return new Accessor\Modules($config['modules'], $config['cache modules'], $config['repository.cache'] . '/core');
	}

	/**
	 * Returns models accessor.
	 *
	 * @return Accessor\Models The models accessor.
	 */
	protected function __get_models()
	{
		return new Accessor\Models($this->modules);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return VarsAccessor The non-volatie variables accessor.
	 */
	protected function __get_vars()
	{
		return new Accessor\Vars(DOCUMENT_ROOT . ltrim($this->config['repository.vars'], DIRECTORY_SEPARATOR));
	}

	/**
	 * Returns the connections accessor.
	 *
	 * @return ConnectionsAccessor
	 */
	protected function __get_connections()
	{
		return new Accessor\Connections($this->config['connections']);
	}

	/**
	 * Getter for the "primary" database connection.
	 *
	 * @return Database
	 */
	protected function __get_db()
	{
		return $this->connections['primary'];
	}

	/**
	 * Returns the configs accessor.
	 *
	 * @return ConfigsAccessor
	 */
	protected function __get_configs()
	{
		return new Accessor\Configs($this);
	}

	/**
	 * Returns the code configuration.
	 *
	 * @return array
	 */
	protected function __get_config()
	{
		$config = $this->configs['core'];

		self::$autoload = $config['autoload'];
		self::$classes_aliases = $config['classes aliases'];

		$this->configs->constructors += $config['config constructors'];

		return $config;
	}

	/**
	 * @var string Code of the locale language.
	 */
	private $_language;

	/**
	 * Sets the locale language to use by the framework.
	 *
	 * @param string $id
	 * @throws Exception if the language identifier is empty.
	 */
	protected function __volatile_set_language($id)
	{
		if (!$id)
		{
			throw new Exception('The language identifier is empty.');
		}

		if ($this->_language == $id)
		{
			return;
		}

		$this->_locale = null;
		$this->_language = $id;
	}

	/**
	 * Returns the locale language code.
	 *
	 * @param string $id
	 */
	protected function __volatile_get_language()
	{
		return $this->_language ? $this->_language : 'en';
	}

	/**
	 * @var WdLocale locale object for the current locale.
	 */
	private $_locale;

	/**
	 * Returns the locale object used by the framework.
	 *
	 * The locale object is reseted when the {@link language} property is set.
	 *
	 * @return WdLocale
	 */
	protected function __volatile_get_locale()
	{
		if ($this->_locale)
		{
			return $this->_locale;
		}

		$this->_locale = $locale = Locale::get($this->language);

		return $locale;
	}

	/**
	 * @var string Timezone used by the framework.
	 */
	private $_timezone;

	/**
	 * Sets the timezone for the framework.
	 *
	 * @param string|int $timezone Name of the timezone, or numeric equivalent e.g. 3600.
	 */
	protected function __volatile_set_timezone($timezone)
	{
		if (is_numeric($timezone))
		{
			$timezone = timezone_name_from_abbr(null, $timezone, 0);
		}

		date_default_timezone_set($timezone);

		$this->_timezone = $timezone;
	}

	/**
	 * Returns the timezone used by the framework.
	 *
	 * @return string The timezone used by the framework.
	 */
	protected function __volatile_get_timezone()
	{
		return $this->_timezone;
	}

	/**
	 * Returns a session.
	 *
	 * The session is initialized when the session object is created.
	 *
	 * @return Session.
	 */
	protected function __get_session()
	{
		$options = $this->config['session'];

		unset($options['id']);
		$session_name = $options['name'];

		if (isset($_POST[$session_name]))
		{
			// FIXME-20110716: support for Flash file upload, we should remove it as fast as possible

			$options['id'] = $_POST[$session_name];
		}

		$session = new Session($options);

		Event::fire('start', array(), $session);

		return $session;
	}

	/**
	 * Run the core object.
	 *
	 * Running the core object implies running startup modules, decoding operation, dispatching
	 * operation.
	 */
	public function run()
	{
		self::$is_running = true;

		$this->modules->autorun = true;

//		wd_log_time('run modules start');
		$this->run_modules();
//		wd_log_time('run modules finish');

		$this->run_context();

//		wd_log_time('run operation start');
		$this->run_operation($_SERVER['REQUEST_PATH'], $_POST + $_GET);
//		wd_log_time('run operation start');
	}

	/**
	 * Run the enabled modules.
	 *
	 * Before the modules are actually ran, their index is used to alter the I18n load paths, the
	 * config paths and the core's `autoload` and `classes aliases` config properties.
	 */
	protected function run_modules()
	{
		$index = $this->modules->index;

		I18n::$load_paths = array_merge(I18n::$load_paths, $index['catalogs']);

		if ($index['configs'])
		{
			$this->configs->add($index['configs'], 5);
		}

		if ($index['autoload'])
		{
			self::$autoload += $index['autoload'];
		}

		if ($index['classes aliases'])
		{
			self::$classes_aliases += $index['classes aliases'];
		}

		if ($index['config constructors'])
		{
			$this->configs->constructors += $index['config constructors'];
		}

		$this->modules->run();
	}

	/**
	 * One can override this method to provide a context for the application.
	 */
	protected function run_context()
	{

	}

	/**
	 * Dispatch the operation associated with the current request, if any.
	 */
	protected function run_operation($uri, array $params)
	{
		$operation = Operation::decode($uri, $params);

		if (!$operation)
		{
			return;
		}

		$operation($operation->params);

		return $operation;
	}
}