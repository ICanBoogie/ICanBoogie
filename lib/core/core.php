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
 * Core of the framework.
 *
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
 * @property-read \ICanBoogie\HTTP\Request $request The request being processed.
 */
class Core extends Object
{
	static private $instance;

	/**
	 * Returns the unique instance of the core object.
	 *
	 * @return Core The core object.
	 */
	public static function get()
	{
		return self::$instance;
	}

	/**
	 * Whether the core is running or not.
	 *
	 * @var boolean
	 */
	public static $is_running = false;

	/**
	 * Echos the exception and kills PHP.
	 *
	 * @param \Exception $exception
	 */
	public static function exception_handler(\Exception $exception)
	{
		Debug::exception_handler($exception);
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
	public function __construct(array $options=array())
	{
		if (self::$instance)
		{
			throw new Exception('Only one instance of the Core object can be created');
		}

		self::$instance = $this;

		#

		$options = array_merge_recursive
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
		date_default_timezone_set('UTC');

		if (get_magic_quotes_gpc())
		{
			$kill_magic_quotes = function()
			{
				$strip_slashes_recursive = function ($value) use(&$strip_slashes_recursive)
				{
					return is_array($value) ? array_map($strip_slashes_recursive, $value) : stripslashes($value);
				};

				$_GET = array_map($strip_slashes_recursive, $_GET);
				$_POST = array_map($strip_slashes_recursive, $_POST);
				$_COOKIE = array_map($strip_slashes_recursive, $_COOKIE);
				$_REQUEST = array_map($strip_slashes_recursive, $_REQUEST);
			};

			$kill_magic_quotes();
		}

		# the order is important, there's magic involved.

		$configs = $this->configs;
		$configs->add($options['paths']['config']);

		$config = array_merge_recursive($options, $this->config);

		I18n::$load_paths = array_merge(I18n::$load_paths, $config['paths']['locale']);

		if ($config['cache configs'])
		{
			$configs->cache_syntheses = true;
			$configs->cache_repository = $config['repository.cache'] . '/core';
		}

		# Initialize events with the "events" config.

		Events::$initializer = function() use($configs)
		{
			return $configs['events'];
		};
	}

	/**
	 * Returns modules accessor.
	 *
	 * @return Modules The modules accessor.
	 */
	protected function get_modules()
	{
		$config = $this->config;

		return new Modules($config['modules'], $config['cache modules'] ? $this->vars : null);
	}

	/**
	 * Returns models accessor.
	 *
	 * @return Models The models accessor.
	 */
	protected function get_models()
	{
		return new Models($this->modules);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return Vars The non-volatie variables accessor.
	 */
	protected function get_vars()
	{
		return new Vars(REPOSITORY . 'vars' . DIRECTORY_SEPARATOR);
	}

	/**
	 * Returns the connections accessor.
	 *
	 * @return Connections
	 */
	protected function get_connections()
	{
		return new Connections($this->config['connections']);
	}

	/**
	 * Getter for the "primary" database connection.
	 *
	 * @return Database
	 */
	protected function get_db()
	{
		return $this->connections['primary'];
	}

	/**
	 * Returns the configs accessor.
	 *
	 * @return ConfigsAccessor
	 */
	protected function get_configs()
	{
		return new Configs($this);
	}

	/**
	 * Returns the _core_ configuration.
	 *
	 * @return array
	 */
	protected function get_config()
	{
		$config = $this->configs['core'];

		self::$autoload = $config['autoload'];
		self::$classes_aliases = $config['classes aliases'];

		$this->configs->constructors += $config['config constructors'];

		return $config;
	}

	/**
	 * Returns the dispatcher used to dispatch HTTP requests.
	 *
	 * @return HTTP\Dispatcher
	 */
	protected function volatile_get_dispatcher()
	{
		return HTTP\get_dispatcher();
	}

	/**
	 * Returns the initial request object.
	 *
	 * @return HTTP\Request
	 */
	protected function get_initial_request()
	{
		return HTTP\Request::from($_SERVER);
	}

	/**
	 * Returns the current request.
	 *
	 * @return HTTP\Request
	 */
	protected function volatile_get_request()
	{
		return HTTP\Request::get_current_request();
	}

	protected function volatile_set_request()
	{
		throw new Exception\PropertyNotWritable(array('request', $this));
	}

	/**
	 * Sets the locale language to use by the framework.
	 *
	 * @param string $id
	 */
	protected function volatile_set_language($id)
	{
		I18n::set_language($id);
	}

	/**
	 * Returns the locale language.
	 *
	 * @param string $id
	 */
	protected function volatile_get_language()
	{
		return I18n::get_language();
	}

	/**
	 * @throws Exception\PropertyNotWritable when the `locale` property is set.
	 */
	protected function volatile_set_locale()
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
	protected function volatile_get_locale()
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
	protected function volatile_set_timezone($timezone)
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
	protected function volatile_get_timezone()
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
	protected function get_session()
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
		Debug::get_config(); // configure debug :(

		self::$is_running = true;

		$this->modules->autorun = true;

		$this->run_modules();

		# Configure the Prototype class with the "prototypes" config.

		Prototype::configure($this->configs['prototypes']);

		$this->run_context();

		if (CACHE_BOOTSTRAP)
		{
			$this->cache_bootstrap();
		}

		$_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);
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
	 * Joins all declared classes also defined in the autoload index into a single file.
	 */
	protected function cache_bootstrap()
	{
		$pathname = BOOTSTRAP_CACHE_PATHNAME;

		if (file_exists($pathname))
		{
			return;
		}

		$strip_comments = function($source)
		{
			if (!function_exists('token_get_all'))
			{
				return $source;
			}

			$output = '';

			foreach (token_get_all($source) as $token)
			{
				if (is_string($token))
				{
					$output .= $token;
				}
				else if ($token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT)
				{
					$output .= '';
				}
				else
				{
					$output .= $token[1];
				}
			}

			return $output;
		};

		$classes = get_declared_classes();
		$autoload = self::$autoload;
		$order = array_intersect_key(array_flip($classes), $autoload);
		$included = array();
		$out = fopen($pathname, 'w');

		fwrite($out, '<?php' . PHP_EOL . PHP_EOL);

		foreach ($order as $class => $weight)
		{
			$pathname = $autoload[$class];

			if (isset($included[$pathname]))
			{
				continue;
			}

			$included[$pathname] = true;

			$in = file_get_contents($pathname);
			$in = $strip_comments($in);
			$in = preg_replace('#^\<\?php\s+#', '', $in);
			$in = trim($in);
			$in = "// original location: $pathname\n\n" . $in . PHP_EOL . PHP_EOL;

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