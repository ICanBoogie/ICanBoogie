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
 * @property \ICanBoogie\Vars $vars Persistent variables accessor.
 * @property \ICanBoogie\Database $db Primary database connection.
 * @property \ICanBoogie\Session $session User's session.
 * @property string $language Locale language.
 * @property string|int $timezone Date and time timezone.
 * @property-read \ICanBoogie\I18n\Locale $locale Locale object matching the locale language.
 * @property array $config The "core" configuration.
 * @property-read \ICanBoogie\HTTP\Request $request The request being processed.
 * @property-read \ICanBoogie\Events $events Events collection.
 * @property-read \ICanBoogie\Routes $routes Routes collection.
 */
class Core extends Object
{
	static private $instance;

	/**
	 * Returns the unique instance of the core object.
	 *
	 * @return Core The core object.
	 */
	static public function get()
	{
		return self::$instance;
	}

	/**
	 * Whether the core is running or not.
	 *
	 * @var boolean
	 */
	static public $is_running = false;

	/**
	 * Echos the exception and kills PHP.
	 *
	 * @param \Exception $exception
	 */
	static public function exception_handler(\Exception $exception)
	{
		Debug::exception_handler($exception);
	}

	static protected $autoload = array();

	/**
	 * Loads the file defining the specified class.
	 *
	 * The 'autoload' config is used to map class name to PHP files.
	 *
	 * Class initializer
	 * -----------------
	 *
	 * If the loaded class defines the '__static_construct' method, the method is invoked to
	 * initialize the class.
	 *
	 * @param string $name Name of the class
	 *
	 * @return boolean Whether or not the required file could be found.
	 */
	static private function autoload_handler($name)
	{
		$list = self::$autoload;

		if (empty($list[$name]))
		{
			return false;
		}

		require_once $list[$name];

		if (method_exists($name, '__static_construct'))
		{
			call_user_func($name . '::__static_construct');
		}

		return true;
	}

	static protected $paths = array();

	static public function add_path($path)
	{
		self::$paths[] = $path;
	}

	/**
	 * Constructor.
	 *
	 * @param array $options Initial options to create the core object.
	 *
	 * @throws \Exception when one tries to create a second instance.
	 */
	public function __construct(array $options=array())
	{
		if (self::$instance)
		{
			throw new \Exception('Only one instance of the Core object can be created');
		}

		self::$instance = $this;

		#

		$options = array_merge_recursive
		(
			array
			(
				'config paths' => array_merge(array(ROOT), self::$paths),
				'locale paths' => array_merge(array(ROOT), self::$paths)
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
		$configs->add($options['config paths']);

		$this->config = $config = array_merge_recursive($options, $this->config);

		#
		# Initial autoload, only autoload configs defined in the config paths are indexed.
		#

		self::$autoload = $this->configs['autoload'];

		#

		I18n::$load_paths = array_merge(I18n::$load_paths, $config['locale paths']);

		#
		# Setting the cache repository to enable config caching.
		#

		if ($config['cache configs'])
		{
			$configs->cache_repository = $config['repository.cache'] . '/core';
		}
	}

	/**
	 * Returns modules accessor.
	 *
	 * @return Modules The modules accessor.
	 */
	protected function get_modules()
	{
		$config = $this->config;

		return new Modules($config['modules paths'], $config['cache modules'] ? $this->vars : null);
	}

	/**
	 * Returns models accessor.
	 *
	 * @return Models The models accessor.
	 */
	protected function get_models()
	{
		return new Models($this->connections, array(), $this->modules);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return Vars The non-volatile variables accessor.
	 */
	protected function get_vars()
	{
		return new Vars(REPOSITORY . 'vars' . DIRECTORY_SEPARATOR);
	}

	/**
	 * Returns the connections accessor.
	 *
	 * @return ActiveRecord\Connections
	 */
	protected function get_connections()
	{
		return new ActiveRecord\Connections($this->config['connections']);
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
	 * @return Configs
	 */
	protected function get_configs()
	{
		return new Configs();
	}

	/**
	 * Returns the _core_ configuration.
	 *
	 * @return array
	 */
	protected function get_config()
	{
		$config = $this->configs['core'];

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
	 * @throws PropertyNotWritable in attempt to write {@link $request}.
	 */
	protected function volatile_set_request()
	{
		throw new PropertyNotWritable(array('request', $this));
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

	/**
	 * Sets the locale language to use by the framework.
	 *
	 * @param string $id
	 */
	protected function volatile_set_language($id)
	{
		throw new PropertyNotWritable(array('language', $this));
	}

	/**
	 * Returns the locale language.
	 *
	 * @return string
	 */
	protected function volatile_get_language()
	{
		return I18n\get_language();
	}

	/**
	 * Sets the working locate.
	 *
	 * @param string $id Locale identifier.
	 */
	protected function volatile_set_locale($id)
	{
		I18n\set_locale($id);
	}

	/**
	 * Returns the working locale object.
	 *
	 * @return I18n\Locale
	 */
	protected function volatile_get_locale()
	{
		return I18n\get_locale();
	}

	/**
	 * @var string The working timezone.
	 */
	private $timezone;

	/**
	 * Sets the working timezone.
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

		$this->timezone = $timezone;
	}

	/**
	 * Returns the working timezone.
	 *
	 * @return string
	 *
	 * @todo should retrun an instance of http://php.net/manual/en/class.datetimezone.php,
	 * __toString() should return its name.
	 */
	protected function volatile_get_timezone()
	{
		return $this->timezone;
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

		return new Session($options);
	}

	/**
	 * Returns the event collection.
	 *
	 * @return \ICanBoogie\Events
	 */
	protected function volatile_get_events()
	{
		return Events::get();
	}

	/**
	 * @throws PropertyNotWritable in attempt to write {@link $events}.
	 */
	protected function volatile_set_events()
	{
		throw new PropertyNotWritable(array('events', $this));
	}

	/**
	 * Returns the route collection.
	 *
	 * @return \ICanBoogie\Routes
	 */
	protected function volatile_get_routes()
	{
		return Routes::get();
	}

	/**
	 * @throws PropertyNotWritable in attempt to write {@link $routes}.
	 */
	protected function volatile_set_routes()
	{
		throw new PropertyNotWritable(array('routes', $this));
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

// 		new Core\BeforeRunEvent($this); TODO-20121127: if we fire an event now, module events won't be taken into account because the events have already been collected

		$events = $this->configs->synthesize('events', function(array $fragments) {

			$events = array();

			foreach ($fragments as $path => $fragment)
			{
				if (empty($fragment['events']))
				{
					continue;
				}

				foreach ($fragment['events'] as $type => $callback)
				{
					if (!is_string($callback))
					{
						throw new \InvalidArgumentException(format
						(
							'Event callback must be a string, %type given: :callback in %path', array
							(
								'type' => gettype($callback),
								'callback' => $callback,
								'path' => $path . 'config/hooks.php'
							)
						));
					}

					#
					# because modules are ordered by weight (most important are first), we can
					# push callbacks instead of unshifting them.
					#

					$events[$type][] = $callback;
				}
			}

			return $events;

		}, 'hooks');

		Events::get()->batch_attach($events);

		#

		Prototype::configure($this->configs['prototypes']);

		new Core\RunEvent($this, $this->initial_request);

		#
		# Register the time it took to run the core.
		#

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
		$modules = $this->modules;
		$index = $modules->index;

		I18n::$load_paths = array_merge(I18n::$load_paths, $modules->locale_paths);

		#
		# add modules config paths to the configs path
		#

		$modules_config_paths = $modules->config_paths;

		if ($modules_config_paths)
		{
			$this->configs->add($modules->config_paths, 5);
		}

		#

		self::$autoload = $this->configs['autoload'] + $this->modules->autoload;

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
}

namespace ICanBoogie\Core;

use ICanBoogie\HTTP\Request;

/**
 * Event class for the `ICanBoogie\Core::run:before` event.
 */
class BeforeRunEvent extends \ICanBoogie\Event
{
	/**
	 * The event is constructed with the type `run:before`.
	 *
	 * @param \ICanBoogie\Core $target
	 */
	public function __construct(\ICanBoogie\Core $target)
	{
		parent::__construct($target, 'run:before', array());
	}
}

/**
 * Event class for the `ICanBoogie\Core::run` event.
 */
class RunEvent extends \ICanBoogie\Event
{
	/**
	 * Initial request.
	 *
	 * @var Request
	 */
	public $request;

	/**
	 * The event is constructed with the type `run`.
	 *
	 * @param \ICanBoogie\Core $target
	 */
	public function __construct(\ICanBoogie\Core $target, Request $request)
	{
		$this->request = $request;

		parent::__construct($target, 'run');
	}
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */