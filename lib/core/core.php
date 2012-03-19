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

/**
 * @property \ICanBoogie\Configs $configs Configurations accessor.
 * @property \ICanBoogie\Connections $connections Database connections accessor.
 * @property \ICanBoogie\Models $models Models accessor.
 * @property \ICanBoogie\Modules $modules Modules accessor.
 * @property \ICanBoogie\Vars $vars Persistant variables accessor.
 * @property \ICanBoogie\Database $db The primary database connection.
 * @property \ICanBoogie\Session $session User's session.
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
		exit(Debug::format_alert($exception));
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
		$list = self::$autoload;

		if (isset($list[$name]))
		{
			require_once $list[$name];

			if (method_exists($name, '__static_construct'))
			{
				call_user_func($name . '::__static_construct');
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
		unset($this->request);

		$options = \ICanBoogie\array_merge_recursive
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

		$config = \ICanBoogie\array_merge_recursive($options, $this->config);

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
	 * @return Modules The modules accessor.
	 */
	protected function __get_modules()
	{
		$config = $this->config;

		return new Modules($config['modules'], $config['cache modules'], $this->vars);
	}

	/**
	 * Returns models accessor.
	 *
	 * @return Models The models accessor.
	 */
	protected function __get_models()
	{
		return new Models($this->modules);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return Vars The non-volatie variables accessor.
	 */
	protected function __get_vars()
	{
		return new Vars(REPOSITORY . 'vars' . DIRECTORY_SEPARATOR);
	}

	/**
	 * Returns the connections accessor.
	 *
	 * @return Connections
	 */
	protected function __get_connections()
	{
		return new Connections($this->config['connections']);
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
		return new Configs($this);
	}

	/**
	 * Returns the _core_ configuration.
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
	 * The initial Request object.
	 *
	 * @var HTTP\Request
	 */
	public $request;

	/**
	 * Returns the initial Request object.
	 *
	 * @return HTTP\Request
	 */
	protected function __get_request()
	{
		return HTTP\Request::from_globals();
	}

	/**
	 * Sets the locale language to use by the framework.
	 *
	 * @param string $id
	 */
	protected function __volatile_set_language($id)
	{
		I18n::set_language($id);
	}

	/**
	 * Returns the locale language.
	 *
	 * @param string $id
	 */
	protected function __volatile_get_language()
	{
		return I18n::get_language();
	}

	/**
	 * @throws Exception\PropertyNotWritable when the `locale` property is set.
	 */
	protected function __volatile_set_locale()
	{
		throw new Exception\PropertyNotWritable(array('locale', $this));
	}

	/**
	 * Returns the locale object used by the framework.
	 *
	 * The locale object is reseted when the {@link language} property is set.
	 *
	 * @return Locale
	 */
	protected function __volatile_get_locale()
	{
		return I18n::get_locale();
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
	 *
	 * @todo should retrun an instance of http://php.net/manual/en/class.datetimezone.php,
	 * __toString() should return its name.
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
	 * Once the session is created the `start` event is fired with the session as sender.
	 *
	 * @return Session.
	 */
	protected function __get_session()
	{
		$options = $this->config['session'];

		unset($options['id']);
		$session_name = $options['name'];

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

		$this->run_modules();

		$this->run_context();

		if ($this->config['cache bootstrap'])
		{
			$this->compact_classes();
		}
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

	// TODO-20120308: refactor this to use the Core\DispatchEvent event.
	protected function run_request(HTTP\Request $request)
	{
		$response = $request();

		if ($response)
		{
			$response();
		}
	}

	/**
	 * Dispatches the request to the appropriate handlers.
	 *
	 * The `ICanBoogie::run` event of class {@link \ICanBoogie\Core\RunEvent} is fired for hooks
	 * to alter a {@link HTTP\Response}. After the event was fired the {@link HTTP\Response} is
	 * invoked.
	 */
	public function dispatch()
	{
// 		session_cache_limiter(false);

		$this->run_request($this->request);

		$response = new HTTP\Response();

		new Core\DispatchEvent($this, array('request' => $this->request, 'response' => $response));

		$response();

		throw new Exception('Invoking the response should have ended the script');
	}

	/**
	 * Joins all declared classes also defined in the autoload index into a single file.
	 */
	protected function compact_classes()
	{
		$path = DOCUMENT_ROOT . 'repository/cache/icanboogie_bootstrap';

		if (file_exists($path))
		{
			return;
		}

		$classes = get_declared_classes();
		$autoload = self::$autoload;

		$order = array_intersect_key(array_flip($classes), $autoload);

		$included = array();
		$out = fopen($path, 'w');

		fwrite($out, '<?php' . PHP_EOL . PHP_EOL);

		foreach ($order as $class => $weight)
		{
			$path = $autoload[$class];

			if (isset($included[$path]))
			{
				continue;
			}

			$included[$path] = true;

			$in = file_get_contents($path);

			$in = preg_replace('#^\<\?php\s+#', '', $in);
			$in = preg_replace('#^\/\*.+\*\/\s+#Us', '', $in);
			$in = trim($in);
			$in = "// original location: $path\n\n" . $in . PHP_EOL . PHP_EOL;

			fwrite($out, $in, strlen($in));
		}

		fclose($out);
	}
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you appart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */

namespace ICanBoogie\Core;

/**
 * Event class for the `ICanBoogie::dispatch` event.
 */

class DispatchEvent extends \ICanBoogie\Event
{
	/**
	 * The HTTP request.
	 *
	 * @var \ICanBoogie\HTTP\Request
	 */
	public $request;

	/**
	 * The HTTP response.
	 *
	 * @var \ICanBoogie\HTTP\Response
	 */
	public $response;

	/**
	 * The event is constructed with the type `dispatch`.
	 *
	 * @param \ICanBoogie\Core $target
	 * @param array $properties
	 */
	public function __construct(\ICanBoogie\Core $target, array $properties)
	{
		parent::__construct($target, 'dispatch', $properties);
	}
}