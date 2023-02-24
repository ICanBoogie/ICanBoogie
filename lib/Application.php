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

use ICanBoogie\Application\BootEvent;
use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\Application\ConfigureEvent;
use ICanBoogie\Application\TerminateEvent;
use ICanBoogie\Autoconfig\Autoconfig;
use ICanBoogie\Config\Builder;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\ResponseStatus;
use ICanBoogie\Storage\Storage;

use function asort;
use function assert;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function header;
use function headers_sent;
use function http_response_code;
use function microtime;
use function set_error_handler;
use function set_exception_handler;

use const SORT_NUMERIC;

/**
 * Application abstract.
 *
 * @property-read bool $is_configured `true` if the application is configured, `false` otherwise.
 * @property-read bool $is_booting `true` if the application is booting, `false` otherwise.
 * @property-read bool $is_booted `true` if the application is booted, `false` otherwise.
 * @property-read bool $is_running `true` if the application is running, `false` otherwise.
 * @property-read bool $is_terminating `true` if the application is terminating, `false` otherwise.
 * @property-read bool $is_terminated `true` if the application is terminated, `false` otherwise.
 * @property Storage $vars Persistent variables registry.
 * @property Session $session User's session.
 * @property string $language Locale language.
 * @property string|int $timezone Time zone.
 * @property-read LoggerInterface $logger The message logger.
 * @property-read Storage $storage_for_configs
 * @property-read Request $request
 */
final class Application implements ConfigProvider, ServiceProvider
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
     * @uses get_vars
     * @uses get_request
     */
    use PrototypeTrait;
    use Binding\Event\ApplicationBindings;
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

    private static Application $instance;

    /**
     * @param array<Autoconfig::*, mixed> $autoconfig
     */
    public static function new(array $autoconfig): self
    {
        if (isset(self::$instance)) {
            throw new ApplicationAlreadyInstantiated();
        }

        return self::$instance = new self($autoconfig);
    }

    /**
     * Returns the unique instance of the application.
     *
     * @throws ApplicationNotInstantiated if the application has not been instantiated yet ({@see new()}).
     */
    public static function get(): Application
    {
        return self::$instance
            ?? throw new ApplicationNotInstantiated();
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
    public readonly array $autoconfig;

    private ?TimeZone $timezone = null;

    /**
     * Sets the working time zone.
     *
     * When the time zone is set the default time zone is also set with
     * {@link date_default_timezone_set()}.
     *
     * @param string|TimeZone $timezone An instance of {@link TimeZone},
     * or the name of a time zone.
     */
    private function set_timezone(string|TimeZone $timezone): void
    {
        $this->timezone = TimeZone::from($timezone);

        date_default_timezone_set((string) $this->timezone);
    }

    /**
     * Returns the working time zone.
     *
     * If the time zone is not defined yet, it defaults to the value of
     * {@link date_default_timezone_get()} or "UTC".
     */
    private function get_timezone(): TimeZone
    {
        return $this->timezone
            ??= TimeZone::from(date_default_timezone_get() ?: 'UTC');
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

    private Storage $vars;

    /**
     * Returns the non-volatile variables registry.
     *
     * @return Storage<string, mixed>
     */
    private function get_vars(): Storage
    {
        return $this->vars
            ??= $this->create_storage($this->config->storage_for_vars);
    }

    public readonly Config $configs;
    public readonly AppConfig $config;
    public readonly EventCollection $events;

    /**
     * @param array<Autoconfig::*, mixed> $autoconfig Initial options to create the application.
     *
     * @throws ApplicationAlreadyInstantiated in attempt to create a second instance.
     */
    private function __construct(array $autoconfig)
    {
        $this->status = self::STATUS_INSTANTIATING;
        $this->autoconfig = $autoconfig;

        if (!date_default_timezone_get()) {
            date_default_timezone_set('UTC');
        }

        $this->configs = $this->create_config_manager(
            /** @phpstan-ignore-next-line */
            $autoconfig[Autoconfig::CONFIG_PATH],
            /** @phpstan-ignore-next-line */
            $autoconfig[Autoconfig::CONFIG_CONSTRUCTOR]
        );
        $this->config = $this->configs->config_for_class(AppConfig::class);
        $this->apply_config($this->config);
        $this->events = \ICanBoogie\Binding\Event\Hooks::get_events($this);

        $this->status = self::STATUS_INSTANTIATED;
    }

    /**
     * @inheritDoc
     */
    public function config_for_class(string $class): object
    {
        return $this->configs->config_for_class($class);
    }

    /**
     * @inheritDoc
     */
    public function service_for_class(string $class): object
    {
        // @phpstan-ignore-next-line
        return $this->container->get($class);
    }

    /**
     * @inheritDoc
     */
    public function service_for_id(string $id, string $class): object
    {
        $service = $this->container->get($id);

        assert($service instanceof $class);

        return $service;
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
     * Returns configuration manager.
     *
     * @param array<string, int> $paths Path list.
     * @param array<class-string, class-string<Builder<object>>> $builders
     */
    private function create_config_manager(array $paths, array $builders): Config
    {
        asort($paths, SORT_NUMERIC);

        return new Config(array_keys($paths), $builders);
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
        return $factory($this);
    }

    /**
     * Changes the status of the application.
     */
    private function change_status(int $status, callable $callable): ?int
    {
        $this->status = $status;
        $rc = $callable();
        $this->status = $status + 1;

        return $rc;
    }

    /**
     * Configures the application.
     *
     * Emits {@link ConfigureEvent} once the application is configured.
     */
    private function configure(): void
    {
        $this->change_status(self::STATUS_CONFIGURING, function () {
            Debug::configure($this->configs->config_for_class(DebugConfig::class));
            Binding\Prototype\AutoConfig::configure($this);

            emit(new ConfigureEvent($this));
        });
    }

    /**
     * Boot the modules and configure Debug, Prototype and Events.
     *
     * Emits {@link BootEvent} after the boot is finished.
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
            emit(new BootEvent($this));

            $_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);
        });
    }

    private Request $request;

    private function get_request(): Request
    {
        // @phpstan-ignore-next-line
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
     * @param Request|null $request The request to handle. If `null`, the initial request is used.
     */
    public function run(Request $request = null): void
    {
        $this->initialize_response_header();
        $this->assert_not_running();

        if (!$this->is_booted) {
            $this->boot();
        }

        $this->change_status(self::STATUS_RUNNING, function () use ($request): void {
            /** @phpstan-ignore-next-line */
            $this->request = $request ??= Request::from($_SERVER);

            emit(new Application\RunEvent($this, $request));

            $response = $this->service_for_class(Responder::class)->respond($request);
            $response();

            $this->terminate($request, $response);
        });
    }

    /**
     * Alias to `run()`
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
     * Emits {@link TerminateEvent}.
     */
    private function terminate(Request $request, Response $response): void
    {
        $this->change_status(self::STATUS_TERMINATING, function () use ($request, $response): void {
            emit(new TerminateEvent($this, $request, $response));
        });
    }

    /**
     * Emits {@link ClearCacheEvent}
     */
    public function clear_cache(): void
    {
        emit(new ClearCacheEvent($this));
    }
}

/*
 * Possessions don't touch you in your heart.
 * Possessions only tear you apart.
 * Possessions cannot kiss you good night.
 * Possessions will never hold you tight.
 */
