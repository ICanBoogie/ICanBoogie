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

use ICanBoogie\Autoconfig\Autoconfig;
use ICanBoogie\Config\Builder;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\ResponseStatus;
use ICanBoogie\Storage\Storage;

use function assert;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function header;
use function headers_sent;
use function http_response_code;
use function is_numeric;
use function json_encode;
use function microtime;
use function set_error_handler;
use function set_exception_handler;
use function timezone_name_from_abbr;
use function trigger_error;

use const E_USER_DEPRECATED;

/**
 * Application abstract.
 *
 * @property-read bool $is_configured `true` if the application is configured, `false` otherwise.
 * @property-read bool $is_booting `true` if the application is booting, `false` otherwise.
 * @property-read bool $is_booted `true` if the application is booted, `false` otherwise.
 * @property-read bool $is_running `true` if the application is running, `false` otherwise.
 * @property-read bool $is_terminating `true` if the application is terminating, `false` otherwise.
 * @property-read bool $is_terminated `true` if the application is terminated, `false` otherwise.
 * @property Config $configs Configurations manager.
 * @property Storage $vars Persistent variables registry.
 * @property Session $session User's session.
 * @property string $language Locale language.
 * @property string|int $timezone Time zone.
 * @property AppConfig $config The "app" configuration.
 * @property-read LoggerInterface $logger The message logger.
 * @property-read Storage $storage_for_configs
 * @property-read Request $request
 */
abstract class ApplicationAbstract
{
    /**
     * @uses get_is_configured
     * @uses get_is_booting
     * @uses get_is_booted
     * @uses get_is_running
     * @uses get_is_terminating
     * @uses get_is_terminated
     * @uses get_timezone
     * @uses set_timezone
     * @uses get_storage_for_configs
     * @uses lazy_get_vars
     * @uses lazy_get_config
     * @uses get_request
     */
    use PrototypeTrait;
    use Binding\Event\ApplicationBindings;
    use Binding\Routing\ApplicationBindings;
    use Binding\SymfonyDependencyInjection\ApplicationBindings;

    /**
     * Status of the application.
     */
    public const STATUS_VOID = 0;
    public const STATUS_INSTANTIATING = 1;
    public const STATUS_INSTANTIATED = 2;
    public const STATUS_CONFIGURING = 3;
    public const STATUS_CONFIGURED = 4;
    public const STATUS_BOOTING = 5;
    public const STATUS_BOOTED = 6;
    public const STATUS_RUNNING = 7;
    public const STATUS_TERMINATING = 8;
    public const STATUS_TERMINATED = 9;

    private static ?Application $instance = null;

    /**
     * Returns the unique instance of the application.
     */
    public static function get(): ?Application
    {
        return self::$instance;
    }

    /**
     * One of `STATUS_*`.
     */
    private int $status = self::STATUS_VOID;

    /**
     * Whether the application is configured.
     */
    private function get_is_configured(): bool
    {
        return $this->status >= self::STATUS_CONFIGURED;
    }

    /**
     * Whether the application is booting.
     */
    private function get_is_booting(): bool
    {
        return $this->status === self::STATUS_BOOTING;
    }

    /**
     * Whether the application is booted.
     */
    private function get_is_booted(): bool
    {
        return $this->status >= self::STATUS_BOOTED;
    }

    /**
     * Whether the application is running.
     */
    private function get_is_running(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * Whether the application is terminating.
     */
    private function get_is_terminating(): bool
    {
        return $this->status === self::STATUS_TERMINATING;
    }

    /**
     * Whether the application is terminated.
     */
    private function get_is_terminated(): bool
    {
        return $this->status === self::STATUS_TERMINATED;
    }

    /**
     * Options passed during construct.
     *
     * @phpstan-var array<Autoconfig::*, mixed>
     */
    public readonly array $auto_config;

    private ?TimeZone $timezone = null;

    /**
     * Sets the working time zone.
     *
     * When the time zone is set the default time zone is also set with
     * {@link date_default_timezone_set()}.
     *
     * @param TimeZone|string|int $timezone An instance of {@link TimeZone},
     * the name of a time zone, or numeric equivalent e.g. 3600.
     */
    private function set_timezone($timezone): void
    {
        if (is_numeric($timezone)) {
            $timezone = timezone_name_from_abbr("", (int) $timezone, 0);
        }

        $this->timezone = TimeZone::from($timezone);

        date_default_timezone_set((string) $this->timezone);
    }

    /**
     * Returns the working time zone.
     *
     * If the time zone is not defined yet it defaults to the value of
     * {@link date_default_timezone_get()} or "UTC".
     */
    private function get_timezone(): TimeZone
    {
        if (!$this->timezone) {
            $this->timezone = TimeZone::from(date_default_timezone_get() ?: 'UTC');
        }

        return $this->timezone;
    }

    /**
     * @var Storage<string, mixed>|null
     */
    private Storage|null $storage_for_configs;

    /**
     * @return Storage<string, mixed>
     */
    private function get_storage_for_configs(): Storage
    {
        return $this->storage_for_configs
            ??= $this->create_storage($this->config->storage_for_config);
    }

    /**
     * Returns the non-volatile variables registry.
     *
     * @return Storage<string, mixed>
     */
    private function lazy_get_vars(): Storage
    {
        return $this->create_storage($this->config->storage_for_vars);
    }

    /**
     * Returns the `app` configuration.
     */
    private function lazy_get_config(): AppConfig
    {
        return $this->configs['app'];
    }

    /**
     * @param array<Autoconfig::*, mixed> $auto_config Initial options to create the application.
     *
     * @throws ApplicationAlreadyInstantiated in attempt to create a second instance.
     */
    public function __construct(array $auto_config = [])
    {
        $this->assert_not_instantiated();

        assert($this instanceof Application);

        self::$instance = $this;

        $this->status = self::STATUS_INSTANTIATING;
        $this->auto_config = $auto_config;

        if (!date_default_timezone_get()) {
            date_default_timezone_set('UTC');
        }

        $this->bind_object_class();
        $this->configs = $this->create_config_manager(
            $auto_config[Autoconfig::CONFIG_PATH],
            $auto_config[Autoconfig::CONFIG_CONSTRUCTOR]
        );
        $this->apply_config($this->config);

        $this->status = self::STATUS_INSTANTIATED;
    }

    /**
     * Asserts that the class is not instantiated yet.
     *
     * @throws ApplicationAlreadyInstantiated if the class is already instantiated.
     */
    private function assert_not_instantiated(): void
    {
        if (self::$instance) {
            throw new ApplicationAlreadyInstantiated();
        }
    }

    /**
     * Asserts that the application is not booted yet.
     *
     * @throws ApplicationAlreadyBooted if the application is already booted.
     */
    private function assert_not_booted(): void
    {
        if ($this->status >= self::STATUS_BOOTING) {
            throw new ApplicationAlreadyBooted();
        }
    }

    /**
     * Asserts that the application is not running yet.
     *
     * @throws ApplicationAlreadyRunning if the application is already running.
     */
    private function assert_not_running(): void
    {
        if ($this->status >= self::STATUS_RUNNING) {
            throw new ApplicationAlreadyRunning();
        }
    }

    /**
     * Binds the object class to our instance.
     */
    private function bind_object_class(): void
    {
        Prototype::from(Prototyped::class)['get_app'] = fn() => $this;
    }

    /**
     * Returns configuration manager.
     *
     * @param array<string, int> $paths Path list.
     * @param array<string, class-string<Builder>> $synthesizers Configuration synthesizers.
     */
    private function create_config_manager(array $paths, array $synthesizers): Config
    {
        return new Config($paths, $synthesizers);
    }

    /**
     * Applies low-level configuration.
     */
    private function apply_config(AppConfig $config): void
    {
        $error_handler = $config->error_handler;

        if ($error_handler) {
            set_error_handler($error_handler);
        }

        $exception_handler = $config->exception_handler;

        if ($exception_handler) {
            set_exception_handler($exception_handler);
        }

        if ($config->cache_configs) {
            $this->configs->cache = $this->get_storage_for_configs();
        }
    }

    /**
     * Creates a storage engine, using a factory.
     *
     * @param callable(Application): Storage<string, mixed> $factory
     *
     * @return Storage<string, mixed>
     */
    private function create_storage(callable $factory): Storage
    {
        assert($this instanceof Application);

        return $factory($this);
    }

    /**
     * Changes the status of the application.
     *
     * @return mixed
     */
    private function change_status(int $status, callable $callable)
    {
        $this->status = $status;
        $rc = $callable();
        $this->status = $status + 1;

        return $rc;
    }

    /**
     * Configures the application.
     *
     * The `configure` event of class {@link Application\ConfigureEvent} is fired after the
     * application is configured. Event hooks may use this event to further configure the
     * application.
     */
    private function configure(): void
    {
        $this->change_status(self::STATUS_CONFIGURING, function () {
            Debug::configure($this->configs['debug']);
            Prototype::bind($this->configs['prototype']);

            $this->events;

            assert($this instanceof Application);

            emit(new Application\ConfigureEvent($this));
        });
    }

    /**
     * Boot the modules and configure Debug, Prototype and Events.
     *
     * The `boot` event of class {@link Application\BootEvent} is fired after the boot is finished.
     *
     * The `ICANBOOGIE_READY_TIME_FLOAT` key is added to the `$_SERVER` super global with the
     * micro-time at which the boot finished.
     *
     * @throws ApplicationAlreadyBooted in attempt to boot the application twice.
     */
    public function boot(): void
    {
        $this->assert_not_booted();

        if (!$this->is_configured) {
            $this->configure();
        }

        $this->change_status(self::STATUS_BOOTING, function () {
            assert($this instanceof Application);

            emit(new Application\BootEvent($this));

            $_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);
        });
    }

    private Request $request;

    private function get_request(): Request
    {
        return $this->request ??= Request::from($_SERVER);
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
     * @param Request<string, mixed>|null $request The request to handle. If `null`, the initial request is used.
     */
    public function run(Request $request = null): void
    {
        $this->initialize_response_header();
        $this->assert_not_running();

        if (!$this->is_booted) {
            $this->boot();
        }

        $this->change_status(self::STATUS_RUNNING, function () use ($request): void {
            $this->request = $request ??= Request::from($_SERVER);

            assert($this instanceof Application);

            emit(new Application\RunEvent($this, $request));

            $response = $this->service_for_class(Responder::class)->respond($request);
            $response();

            $this->terminate($request, $response);
        });
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function service_for_class(string $class): object
    {
        return $this->container->get($class);
    }

    /**
     * Alias to `run()`
     *
     * @param Request<string, mixed>|null $request
     */
    public function __invoke(Request $request = null): void
    {
        $this->run($request);
    }

    /**
     * Initializes default response header.
     *
     * The default response has the {@link ResponseStatus::STATUS_INTERNAL_SERVER_ERROR} status code and the appropriate
     * header fields, so it is not cached. That way, if something goes wrong and an error message is displayed it won't
     * be cached by a proxy.
     */
    private function initialize_response_header(): void
    {
        http_response_code(ResponseStatus::STATUS_INTERNAL_SERVER_ERROR);

        // @codeCoverageIgnoreStart
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Terminate the application.
     *
     * Fires the `ICanBoogie\Application::terminate` event of class
     * {@link Application\TerminateEvent}.
     *
     * @param Request<string, mixed> $request
     */
    private function terminate(Request $request, Response $response): void
    {
        $this->change_status(self::STATUS_TERMINATING, function () use ($request, $response): void {
            assert($this instanceof Application);

            emit(new Application\TerminateEvent($this, $request, $response));
        });
    }

    /**
     * Fires the `ICanBoogie\Application::clear_cache` event.
     */
    public function clear_cache(): void
    {
        assert($this instanceof Application);

        emit(new Application\ClearCacheEvent($this));
    }
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */
