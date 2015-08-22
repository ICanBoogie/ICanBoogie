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

use ICanBoogie\Binding\Event\CoreBindings as EventBindings;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\Storage\FileStorage;

/**
 * Core of the framework.
 *
 * @property-read bool $is_configured `true` if the core is configured, `false` otherwise.
 * @property-read bool $is_booting `true` if the core is booting, `false` otherwise.
 * @property-read bool $is_booted `true` if the core is booted, `false` otherwise.
 * @property-read bool $is_running `true` if the core is running, `false` otherwise.
 * @property Config $configs Configurations manager.
 * @property FileStorage $vars Persistent variables registry.
 * @property Session $session User's session.
 * @property string $language Locale language.
 * @property string|int $timezone Time zone.
 * @property array $config The "core" configuration.
 * @property-read Request $request The request being processed.
 * @property Request $initial_request The initial request.
 * @property-read LoggerInterface $logger The message logger.
 */
class Core
{
	use PrototypeTrait;
	use EventBindings;

	/**
	 * Status of the application.
	 */
	const STATUS_VOID = 0;
	const STATUS_INSTANTIATING = 1;
	const STATUS_INSTANTIATED = 2;
	const STATUS_CONFIGURING = 3;
	const STATUS_CONFIGURED = 4;
	const STATUS_BOOTING = 5;
	const STATUS_BOOTED = 6;
	const STATUS_RUNNING = 7;
	const STATUS_TERMINATED = 8;

	/**
	 * One of `STATUS_*`.
	 *
	 * @var int
	 */
	static private $status = self::STATUS_VOID;

	/**
	 * Whether the core is configured.
	 *
	 * @return bool `true` if the core is configured, `false` otherwise.
	 */
	protected function get_is_configured()
	{
		return self::$status >= self::STATUS_CONFIGURED;
	}

	/**
	 * Whether the core is booting.
	 *
	 * @return bool `true` if the core is booting, `false` otherwise.
	 */
	protected function get_is_booting()
	{
		return self::$status === self::STATUS_BOOTING;
	}

	/**
	 * Whether the core is booted.
	 *
	 * @return bool `true` if the core is booted, `false` otherwise.
	 */
	protected function get_is_booted()
	{
		return self::$status >= self::STATUS_BOOTED;
	}

	/**
	 * Whether the core is running.
	 *
	 * @return bool `true` if the core is running, `false` otherwise.
	 */
	protected function get_is_running()
	{
		return self::$status === self::STATUS_RUNNING;
	}

	/**
     * Options passed during construct.
     *
     * @var array
     */
    static private $construct_options = [];

	/**
	 * @var Core
	 */
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
	 * Constructor.
	 *
	 * @param array $options Initial options to create the core object.
	 *
	 * @throws CoreAlreadyInstantiated in attempt to create a second instance.
	 */
	public function __construct(array $options = [])
	{
		$this->assert_not_instantiated();

		self::$status = self::STATUS_INSTANTIATING;
		self::$instance = $this;
        self::$construct_options = $options;

		if (!date_default_timezone_get())
		{
			date_default_timezone_set('UTC');
		}

		$this->bind_object_class();

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
			$configs->cache = new FileStorage(REPOSITORY . 'cache' . DIRECTORY_SEPARATOR . 'configs');
		}

		self::$status = self::STATUS_INSTANTIATED;
	}

	/**
	 * Asserts that the class is not instantiated yet.
	 *
	 * @throws CoreAlreadyInstantiated if the class is already instantiated.
	 */
	private function assert_not_instantiated()
	{
		if (self::$instance)
		{
			throw new CoreAlreadyInstantiated;
		}
	}

	/**
	 * Asserts that the application is not booted yet.
	 *
	 * @throws CoreAlreadyBooted if the application is already booted.
	 */
	public function assert_not_booted()
	{
		if (self::$status >= self::STATUS_BOOTING)
		{
			throw new CoreAlreadyBooted;
		}
	}

	/**
	 * Asserts that the application is not running yet.
	 *
	 * @throws CoreAlreadyRunning if the application is already running.
	 */
	public function assert_not_running()
	{
		if (self::$status >= self::STATUS_RUNNING)
		{
			throw new CoreAlreadyRunning;
		}
	}

	/**
	 * Binds the object class to our instance.
	 */
	private function bind_object_class()
	{
		Prototype::from(Object::class)['get_app'] = function() {

			return $this;

		};
	}

	/**
	 * Returns configuration manager.
	 *
	 * @param array $paths Path list.
	 * @param array $synthesizers Configuration synthesizers.
	 *
	 * @return Config
	 */
	protected function create_config_manager(array $paths, array $synthesizers)
	{
		return new Config($paths, $synthesizers);
	}

	/**
	 * Returns the non-volatile variables registry.
	 *
	 * @return FileStorage
	 */
	protected function lazy_get_vars()
	{
		return new FileStorage(REPOSITORY . 'vars');
	}

	/**
	 * Returns the _core_ configuration.
	 *
	 * @return array
	 */
	protected function lazy_get_config()
	{
		return array_merge_recursive($this->configs['core'], self::$construct_options);
    }

	/**
	 * Returns the initial request object.
	 *
	 * @return Request
	 */
	protected function lazy_get_initial_request()
	{
		return HTTP\get_initial_request();
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
	 * Returns HTTP dispatcher.
	 *
	 * @return HTTP\Dispatcher
	 */
	protected function get_dispatcher()
	{
		return HTTP\get_dispatcher();
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
	 * @param TimeZone|string|int $timezone An instance of {@link TimeZone},
	 * the name of a time zone, or numeric equivalent e.g. 3600.
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
	 * @return TimeZone
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
	 * Changes the status of the application.
	 *
	 * @param int $status
	 * @param callable $callable
	 *
	 * @return mixed
	 */
	protected function change_status($status, callable $callable)
	{
		self::$status = $status;
		$rc = $callable();
		self::$status = $status + 1;

		return $rc;
	}

	/**
	 * Configures the application.
	 *
	 * The `configure` event of class {@link Core\ConfigureEvent} is fired after the application
	 * is configured. Event hooks may use this event to further configure the application.
	 */
	protected function configure()
	{
		$this->change_status(self::STATUS_CONFIGURING, function() {

			Debug::configure($this->configs['debug']);
			Prototype::configure($this->configs['prototype']);

			$this->events;

			new Core\ConfigureEvent($this);

		});
	}

	/**
	 * Boot the modules and configure Debug, Prototype and Events.
	 *
	 * The `boot` event of class {@link Core\BootEvent} is fired after the boot is finished.
	 *
	 * The `ICANBOOGIE_READY_TIME_FLOAT` key is added to the `$_SERVER` super global with the
	 * micro-time at which the boot finished.
	 *
	 * @throws CoreAlreadyBooted in attempt to boot the core twice.
	 */
	public function boot()
	{
		$this->assert_not_booted();

		if (!$this->is_configured)
		{
			$this->configure();
		}

		$this->change_status(self::STATUS_BOOTING, function() {

			new Core\BootEvent($this);

			$_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);

		});
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

		$this->assert_not_running();

		if (!$this->is_booted)
		{
			$this->boot();
		}

		$this->change_status(self::STATUS_RUNNING, function() {

			$request = $this->initial_request;

			$this->run($request);

			$response = $request();
			$response();

			$this->terminate($request, $response);

		});
	}

	/**
     * Fires the `ICanBoogie\Core::run` event.
     *
     * @param Request $request
     */
    protected function run(Request $request)
    {
	    new Core\RunEvent($this, $request);
    }

	/**
	 * Terminate the application.
	 *
	 * The method throws the `ICanBoogie\Core::terminate` event of class
	 * {@link Core\TerminateEvent}.
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	protected function terminate(Request $request, Response $response)
	{
		new Core\TerminateEvent($this, $request, $response);
	}
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */
