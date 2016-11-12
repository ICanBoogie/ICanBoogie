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
use ICanBoogie\Binding\HTTP\CoreBindings as HTTPBindings;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Storage\Storage;

/**
 * Core of the ICanBoogie framework.
 *
 * @property-read bool $is_configured `true` if the application is configured, `false` otherwise.
 * @property-read bool $is_booting `true` if the application is booting, `false` otherwise.
 * @property-read bool $is_booted `true` if the application is booted, `false` otherwise.
 * @property-read bool $is_running `true` if the application is running, `false` otherwise.
 * @property Config $configs Configurations manager.
 * @property Storage $vars Persistent variables registry.
 * @property Session $session User's session.
 * @property string $language Locale language.
 * @property string|int $timezone Time zone.
 * @property array $config The "core" configuration.
 * @property-read LoggerInterface $logger The message logger.
 * @property-read Storage $storage_for_configs
 */
abstract class Core
{
	use PrototypeTrait;
	use EventBindings, HTTPBindings;

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
	 * Whether the application is configured.
	 *
	 * @return bool `true` if the application is configured, `false` otherwise.
	 */
	protected function get_is_configured()
	{
		return self::$status >= self::STATUS_CONFIGURED;
	}

	/**
	 * Whether the application is booting.
	 *
	 * @return bool `true` if the application is booting, `false` otherwise.
	 */
	protected function get_is_booting()
	{
		return self::$status === self::STATUS_BOOTING;
	}

	/**
	 * Whether the application is booted.
	 *
	 * @return bool `true` if the application is booted, `false` otherwise.
	 */
	protected function get_is_booted()
	{
		return self::$status >= self::STATUS_BOOTED;
	}

	/**
	 * Whether the application is running.
	 *
	 * @return bool `true` if the application is running, `false` otherwise.
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
	 * @var Application
	 */
	static private $instance;

	/**
	 * Returns the unique instance of the application.
	 *
	 * @return Application
	 */
	static public function get()
	{
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @param array $options Initial options to create the application.
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
		$this->configs = $this->create_config_manager($options['config-path'], $options['config-constructor']);
		$this->apply_config($this->config);

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
	 * @throws ApplicationAlreadyBooted if the application is already booted.
	 */
	public function assert_not_booted()
	{
		if (self::$status >= self::STATUS_BOOTING)
		{
			throw new ApplicationAlreadyBooted;
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
		Prototype::from(Prototyped::class)['get_app'] = function() {

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
	 * Applies low-level configuration.
	 *
	 * @param array $config
	 */
	protected function apply_config(array $config)
	{
		$error_handler = $config['error_handler'];

		if ($error_handler)
		{
			set_error_handler($error_handler);
		}

		$exception_handler = $config['exception_handler'];

		if ($exception_handler)
		{
			set_exception_handler($exception_handler);
		}

		if ($config['cache configs'])
		{
			$this->configs->cache = $this->storage_for_configs;
		}
	}

	/**
	 * Creates a storage engine.
	 *
	 * @param string|callable $engine A class name or a callable.
	 *
	 * @return Storage
	 */
	protected function create_storage($engine)
	{
		if (class_exists($engine))
		{
			return new $engine;
		}

		if (is_string($engine) && version_compare(PHP_VERSION, 7, '<') && strpos($engine, '::') !== false)
		{
			$engine = explode('::', $engine);
		}

		return $engine($this);
	}

	/**
	 * Creates storage engine for synthesized configs.
	 *
	 * @param string|callable $engine A class name or a callable.
	 *
	 * @return Storage
	 */
	protected function create_storage_for_configs($engine)
	{
		return $this->create_storage($engine);
	}

	/**
	 * @return Storage
	 */
	protected function get_storage_for_configs()
	{
		static $storage;

		return $storage
			?: $storage = $this->create_storage_for_configs($this->config['storage_for_configs']);
	}

	/**
	 * Creates storage engine for variables.
	 *
	 * @param string|callable $engine A class name or a callable.
	 *
	 * @return Storage
	 */
	protected function create_storage_for_vars($engine)
	{
		return $this->create_storage($engine);
	}

	/**
	 * Returns the non-volatile variables registry.
	 *
	 * @return Storage
	 */
	protected function lazy_get_vars()
	{
		return $this->create_storage_for_vars($this->config['storage_for_vars']);
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
			Prototype::bind($this->configs['prototype']);

			$this->events;

			/* @var $this Application */

			new Application\ConfigureEvent($this);

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
	 * @throws ApplicationAlreadyBooted in attempt to boot the application twice.
	 */
	public function boot()
	{
		$this->assert_not_booted();

		if (!$this->is_configured)
		{
			$this->configure();
		}

		$this->change_status(self::STATUS_BOOTING, function() {

			/* @var $this Application */

			new Application\BootEvent($this);

			$_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);

		});
	}

	/**
	 * Run the application.
	 *
	 * In order to avoid error messages triggered by PHP fatal errors to be send with a 200 (Ok)
	 * HTTP code, the HTTP code is changed to 500 before the application is run (and booted). When
	 * the process runs properly the HTTP code is changed to the appropriate value by the response.
	 *
	 * The {@link boot()} method is invoked if the application has not booted yet.
	 *
	 * @param Request|null $request The request to handle. If `null`, the initial request is used.
	 */
	public function __invoke(Request $request = null)
	{
		$this->initialize_response_header();
		$this->assert_not_running();

		if (!$this->is_booted)
		{
			$this->boot();
		}

		$this->change_status(self::STATUS_RUNNING, function() use ($request) {

			if (!$request)
			{
				$request = $this->initial_request;
			}

			$this->run($request);

			$response = $request();
			$response();

			$this->terminate($request, $response);

		});
	}

	/**
     * Fires the `ICanBoogie\Application::run` event.
     *
     * @param Request $request
     */
    protected function run(Request $request)
    {
	    /* @var $this Application */

	    new Application\RunEvent($this, $request);
    }

	/**
	 * Terminate the application.
	 *
	 * Fires the `ICanBoogie\Application::terminate` event of class
	 * {@link Application\TerminateEvent}.
	 *
	 * @param Request $request
	 * @param Response $response
	 */
	protected function terminate(Request $request, Response $response)
	{
		/* @var $this Application */

		new Application\TerminateEvent($this, $request, $response);
	}

	/**
	 * Initializes default response header.
	 *
	 * The default response has the {@link Status::INTERNAL_SERVER_ERROR} status code and
	 * the appropriate header fields so it is not cached. That way, if something goes wrong
	 * and an error message is displayed it won't be cached by a proxi.
	 */
	protected function initialize_response_header()
	{
		http_response_code(Status::INTERNAL_SERVER_ERROR);

		// @codeCoverageIgnoreStart
		if (!headers_sent())
		{
			header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
			header('Pragma: no-cache');
			header('Expires: 0');
		}
		// @codeCoverageIgnoreEnd
	}
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */
