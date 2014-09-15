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
 * @property \ICanBoogie\ActiveRecord\Connections $connections Database connections accessor.
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
 * @property-read \ICanBoogie\Events $events Event collection.
 * @property-read \ICanBoogie\Routing\Routes $routes Route collection.
 * @property-read \ICanBoogie\LoggerInterface $logger The message logger.
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

	/**
	 * Constructor.
	 *
	 * @param array $options Initial options to create the core object.
	 *
	 * @throws \Exception when one tries to create a second instance.
	 */
	public function __construct(array $options=[])
	{
		if (self::$instance)
		{
			throw new \Exception('Only one instance of the Core object can be created');
		}

		self::$instance = $this;

		if (php_sapi_name() !== 'cli')
		{
			$class = get_class($this);

			set_exception_handler($class . '::exception_handler');
			set_error_handler('ICanBoogie\Debug::error_handler');
		}

		if (!date_default_timezone_get())
		{
			date_default_timezone_set('UTC');
		}

		$this->configs = $configs = $this->create_config_manager($options['config-path'], $options['config-constructor']);

		$config = $this->config;

		$this->config['locale-path'] = $options['locale-path'];
		$this->config['module-path'] = $options['module-path'];

		#

		if (class_exists('ICanBoogie\I18n', true))
		{
			I18n::$load_paths = array_merge(I18n::$load_paths, $options['locale-path']);
		}

		#
		# Setting the cache repository to enable config caching.
		#

		if ($config['cache configs'])
		{
			$configs->cache_repository = $config['repository.cache'] . '/core';
		}
	}

	protected function create_config_manager($path_list, $constructors)
	{
		return new Configs($path_list, $constructors);
	}

	/**
	 * Returns modules accessor.
	 *
	 * @return Modules The modules accessor.
	 */
	protected function lazy_get_modules()
	{
		$config = $this->config;

		return new Modules($config['module-path'], $config['cache modules'] ? $this->vars : null);
	}

	/**
	 * Returns models accessor.
	 *
	 * @return Models The models accessor.
	 */
	protected function lazy_get_models()
	{
		return new Models($this->connections, [], $this->modules);
	}

	/**
	 * Returns the non-volatile variables accessor.
	 *
	 * @return Vars The non-volatile variables accessor.
	 */
	protected function lazy_get_vars()
	{
		return new Vars(REPOSITORY . 'vars' . DIRECTORY_SEPARATOR);
	}

	/**
	 * Returns the _core_ configuration.
	 *
	 * @return array
	 */
	protected function lazy_get_config()
	{
		$config = $this->configs['core'];

		return $config;
	}

	/**
	 * Returns the dispatcher used to dispatch HTTP requests.
	 *
	 * @return HTTP\Dispatcher
	 */
	protected function get_dispatcher()
	{
		return HTTP\get_dispatcher();
	}

	/**
	 * Returns the initial request object.
	 *
	 * @return HTTP\Request
	 */
	protected function lazy_get_initial_request()
	{
		return HTTP\Request::from($_SERVER);
	}

	/**
	 * Returns the current request.
	 *
	 * @return HTTP\Request
	 */
	protected function get_request()
	{
		return HTTP\Request::get_current_request() ?: $this->initial_request;
	}

	/**
	 * Returns the locale language.
	 *
	 * @return string
	 */
	protected function get_language() // TODO-20140915: this method should be a prototype method
	{
		if (!class_exists('ICanBoogie\I18n', true))
		{
			return 'en';
		}

		return I18n\get_language();
	}

	/**
	 * Sets the working locate.
	 *
	 * @param string $id Locale identifier.
	 */
	protected function set_locale($id) // TODO-20140915: this method should be a prototype method
	{
		if (!class_exists('ICanBoogie\I18n', true))
		{
			return;
		}

		I18n\set_locale($id);
	}

	/**
	 * Returns the working locale object.
	 *
	 * @return I18n\Locale
	 */
	protected function get_locale() // TODO-20140915: this method should be a prototype method
	{
		return I18n\get_locale();
	}

	/**
	 * @var string The working time zone.
	 */
	private $timezone;

	/**
	 * Sets the working time zone.
	 *
	 * When the time zone is set the default time zone is also set with
	 * {@link date_default_timezone_set()}.
	 *
	 * @param \ICanBoogie\Timezone|string|int $timezone An instance of {@link TimeZone},
	 * the name of a timezone, or numeric equivalent e.g. 3600.
	 */
	protected function set_timezone($timezone)
	{
		if (is_numeric($timezone))
		{
			$timezone = timezone_name_from_abbr(null, $timezone, 0);
		}

		$this->timezone = TimeZone::from($timezone);

		date_default_timezone_set((string) $this->timezone);
	}

	/**
	 * Returns the working time zone.
	 *
	 * If the time zone is not defined yet it defaults to the value of
	 * {@link date_default_timezone_get()} or "UTC".
	 *
	 * @return string
	 */
	protected function get_timezone()
	{
		if (!$this->timezone)
		{
			$this->timezone = TimeZone::from(date_default_timezone_get() ?: 'UTC');
		}

		return $this->timezone;
	}

	/**
	 * Launch the application.
	 */
	public function __invoke()
	{
		self::$is_running = true;

		Debug::configure($this->configs['debug']);

		$this->run_modules();

		Prototype::configure($this->configs['prototypes']);

		Events::patch('get', function() { // TODO-20140310: deprecate

			return $this->events;

		});

		new Core\RunEvent($this, $this->initial_request);

		#
		# Register the time at which the core was running.
		#

		$_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);

		return $this->initial_request;
	}

	/**
	 * Run the enabled modules.
	 *
	 * Before the modules are actually ran, their index is used to alter the I18n load paths, the
	 * config paths and the core's `classes aliases` config properties.
	 */
	protected function run_modules()
	{
		$modules = $this->modules;
		$index = $modules->index;

		if (class_exists('ICanBoogie\I18n', true))
		{
			I18n::$load_paths = array_merge(I18n::$load_paths, $modules->locale_paths);
		}

		#
		# add modules config paths to the configs path
		#

		$modules_config_paths = $modules->config_paths;

		if ($modules_config_paths)
		{
			$this->configs->add($modules->config_paths, -10);
		}
	}

	/**
	 * Genreates a path with the specified parameters.
	 *
	 * @param strign|Route $pattern_or_route_id_or_route A pattern, a route identifier or a
	 * {@link Route} instance.
	 * @param string $params
	 * @param array $options
	 *
	 * @return string
	 */
	public function generate_path($pattern_or_route_id_or_route, $params=null, array $options=[])
	{
		if ($pattern_or_route_id_or_route instanceof Route)
		{
			$path = $pattern_or_route_id_or_route->format($params);
		}
		else if (isset($this->routes[$pattern_or_route_id_or_route]))
		{
			$path = $this->routes[$pattern_or_route_id_or_route]->format($params);
		}
		else if (Route::is_pattern($pattern_or_route_id_or_route))
		{
			$path = Routing\Pattern::from($pattern_or_route_id_or_route)->format($params);
		}
		else
		{
			throw new \InvalidArgumentException("Invalid \$pattern_or_route_id_or_route.");
		}

		return Routing\contextualize($path);
	}

	public function generate_url($pattern_or_route_id_or_route, $params=null, array $options=[])
	{
		return $this->site->url . $this->generate_path($pattern_or_route_id_or_route, $params, $options);
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
		parent::__construct($target, 'run:before');
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