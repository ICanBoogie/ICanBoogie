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

use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Routing\Route;

/**
 * Core of the framework.
 *
 * @property \ICanBoogie\Configs $configs Configurations manager.
 * @property \ICanBoogie\ActiveRecord\Connections $connections Database connections provider.
 * @property \ICanBoogie\Module\Models $models Models provider.
 * @property \ICanBoogie\Module\Modules $modules Modules provider.
 * @property \ICanBoogie\Vars $vars Persistent variables registry.
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
	 * Constructor.
	 *
	 * @param array $options Initial options to create the core object.
	 *
	 * @throws \Exception when one tries to create a second instance.
	 */
	public function __construct(array $options=[])
	{
		#
		# instance
		#

		if (self::$instance)
		{
			throw new \Exception('Only one instance of the Core object can be created');
		}

		self::$instance = $this;

		Prototype::from('ICanBoogie\Object')['get_app'] = function() {

			return $this;

		};

		#

		if (!date_default_timezone_get())
		{
			date_default_timezone_set('UTC');
		}

		#
		# config
		#

		$this->configs = $configs = $this->create_config_manager($options['config-path'], $options['config-constructor']);
		$config = $this->config;

		if ($config['error_handler'])
		{
			set_error_handler($config['error_handler']);
		}

		if ($config['exception_handler'])
		{
			set_exception_handler($config['exception_handler']);
		}

		if ($config['cache configs'])
		{
			$configs->cache = new Vars(REPOSITORY . 'cache' . DIRECTORY_SEPARATOR . 'configs');
		}

		$this->config['locale-path'] = $options['locale-path'];
		$this->config['module-path'] = $options['module-path'];

		#

		if (class_exists('ICanBoogie\I18n', true))
		{
			I18n::$load_paths = array_merge(I18n::$load_paths, $options['locale-path']);
		}
	}

	protected function create_config_manager($path_list, $constructors)
	{
		return new Configs($path_list, $constructors);
	}

	/**
	 * Returns the non-volatile variables registry.
	 *
	 * @return Vars
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
	 * @return Request
	 */
	protected function lazy_get_initial_request()
	{
		return Request::from($_SERVER);
	}

	/**
	 * Returns the current request.
	 *
	 * @return Request
	 */
	protected function get_request()
	{
		return Request::get_current_request() ?: $this->initial_request;
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

	static private $is_booted;

	protected function get_is_booted()
	{
		return self::$is_booted === true;
	}

	protected function get_is_booting()
	{
		return self::$is_booted === false;
	}

	/**
	 * Boot the modules and configure Debug, Prototype and Events.
	 *
	 * The `boot` event of class {@link Core\BootEvent} is fired after the boot is finished.
	 *
	 * The `ICANBOOGIE_READY_TIME_FLOAT` key is added to the `$_SERVER` super global with the
	 * micro time at which the boot finished.
	 *
	 * @throws CoreAlreadyBooted in attempt to boot the core twice.
	 */
	public function boot()
	{
		if (self::$is_booted !== null)
		{
			throw new CoreAlreadyBooted;
		}

		self::$is_booted = false;

		Debug::configure($this->configs['debug']);
		Prototype::configure($this->configs['prototypes']);
		Events::patch('get', function() {

			return $this->events;

		});

		new Core\BootEvent($this);

		$_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);

		self::$is_booted = true;
	}

	/**
	 * Run the application.
	 *
	 * In order to avoid error messages triggered by PHP fatal errors to be send with a 200 (Ok)
	 * HTTP code, the HTTP code is changed to 500 before the core is run (and booted). When the
	 * process runs properly the HTTP code is changed to the appropriate value by the response.
	 *
	 * The {@link boot()} method is invoked if the core has not booted yet.
	 */
	public function __invoke()
	{
		http_response_code(500);

		if (self::$is_booted === null)
		{
			$this->boot();
		}

		self::$is_running = true;

		$request = $this->initial_request;

		new Core\RunEvent($this, $request);

		$response = $request();
		$response();

		$this->terminate($request, $response);
	}

	/**
	 * Terminate the application.
	 *
	 * The method throws the `ICanBoogie\Core::run` event of class
	 * {@link \ICanboogie\Core\RunEvent}.
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	protected function terminate(Request $request, Response $response)
	{
		new Core\TerminateEvent($this, $request, $response);
	}

	/**
	 * Generates a path with the specified parameters.
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

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */
