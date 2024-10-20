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
use ICanBoogie\Application\InvalidState;
use ICanBoogie\Application\RunEvent;
use ICanBoogie\Application\TerminateEvent;
use ICanBoogie\Autoconfig\Autoconfig;
use ICanBoogie\Binding\SymfonyDependencyInjection\ContainerFactory;
use ICanBoogie\Config\Builder;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\ResponseStatus;
use ICanBoogie\Storage\Storage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

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

    /**
     * Status of the application.
     */
    public const STATUS_VOID = 0;
    public const STATUS_BOOTING = 5;
    public const STATUS_BOOTED = 6;
    public const STATUS_RUNNING = 7;
    public const STATUS_TERMINATING = 8;
    public const STATUS_TERMINATED = 9;

    private static Application $instance;

    /**
     * @throws InvalidState
     */
    public static function new(Autoconfig $autoconfig): self
    {
        if (isset(self::$instance)) {
            throw InvalidState::already_instantiated();
        }

        return self::$instance = new self($autoconfig);
    }

    /**
     * Returns the unique instance of the application.
     *
     * @throws InvalidState
     */
    public static function get(): Application
    {
        return self::$instance
            ?? throw InvalidState::not_instantiated();
    }

    /**
     * One of `STATUS_*`.
     */
    private int $status = self::STATUS_VOID;

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

    public readonly Autoconfig $autoconfig;

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
        /** @var TimeZone */
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
            /** @phpstan-ignore-next-line */
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
            /** @phpstan-ignore-next-line */
            ??= $this->create_storage($this->config->storage_for_vars);
    }

    public readonly Config $configs;
    public readonly AppConfig $config;
    public readonly EventCollection $events;
    public readonly ContainerInterface $container;

    private function __construct(Autoconfig $autoconfig)
    {
        $this->autoconfig = $autoconfig;

        if (!date_default_timezone_get()) {
            date_default_timezone_set('UTC');
        }

        $this->configs = $this->create_config_provider(
            $autoconfig->config_paths,
            $autoconfig->config_builders,
        );
        $this->config = $this->configs->config_for_class(AppConfig::class);
        $this->apply_config($this->config);

        // The container can be created once configurations are available.

        $this->container = ContainerFactory::from($this);

        // Enable the usage of `ref()`.

        \ICanBoogie\Service\ServiceProvider::define(
            fn (string $id): object => $this->container->get($id)
        );

        // Events can be set up once the container is available.

        $this->events = $this->service_for_class(EventCollection::class);

        // Enable the usage of `emit()`.

        EventCollectionProvider::define(fn() => $this->events);
    }

    public function config_for_class(string $class): object
    {
        return $this->configs->config_for_class($class);
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
        /** @var T */
        return $this->container->get($class);
    }

    public function service_for_id(string $id, string $class): object
    {
        $service = $this->container->get($id);

        assert($service instanceof $class, "The service is not of the expected class");

        return $service;
    }

    /**
     * Creates the configuration provider.
     *
     * @param array<string, int> $paths Path list.
     * @param array<class-string, class-string<Builder<object>>> $builders
     */
    private function create_config_provider(array $paths, array $builders): Config
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
     * Boot the modules and configure Debug, Prototype, and Events.
     *
     * Emits {@link BootEvent} after the boot is finished.
     *
     * The `ICANBOOGIE_READY_TIME_FLOAT` key is added to the `$_SERVER` super global with the
     * micro-time at which the boot finished.
     */
    public function boot(): void
    {
        $this->assert_can_boot();

        $this->status = self::STATUS_BOOTING;

        Debug::configure($this);
        Binding\Prototype\AutoConfig::configure($this);

        emit(new BootEvent($this));

        $_SERVER['ICANBOOGIE_READY_TIME_FLOAT'] = microtime(true);

        $this->status = self::STATUS_BOOTED;
    }

    /**
     * Asserts that the application is not booted yet.
     *
     * @throws InvalidState
     */
    private function assert_can_boot(): void
    {
        if ($this->status >= self::STATUS_BOOTING) {
            throw InvalidState::already_booted();
        }
    }

    private Request $request;

    private function get_request(): Request
    {
        /** @var Request */
        return $this->request ??= Request::from($_SERVER);
    }

    /**
     * Run the application.
     *
     * To avoid error messages triggered by PHP fatal errors to be sent with a 200 (Ok) HTTP code, the HTTP code is
     * changed to 500 before the application is run (and booted). When the process runs properly, the response changes
     * the HTTP code to the appropriate value.
     *
     * @param Request|null $request The request to handle. If `null`, a request is created from `$_SERVER`.
     */
    public function run(Request $request = null): void
    {
        $this->initialize_response_header();
        $this->assert_can_run();

        $this->status = self::STATUS_RUNNING;

        $this->request = $request ??= Request::from($_SERVER);

        emit(new RunEvent($this, $request));

        $response = $this->service_for_class(Responder::class)->respond($request);
        $response();

        $this->terminate($request, $response);
    }

    /**
     * Asserts that the application is not running yet.
     *
     * @throws InvalidState
     */
    private function assert_can_run(): void
    {
        if ($this->status < self::STATUS_BOOTED) {
            throw InvalidState::not_booted();
        }

        if ($this->status >= self::STATUS_RUNNING) {
            throw InvalidState::already_running();
        }
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
        $this->status = self::STATUS_TERMINATING;

        emit(new TerminateEvent($this, $request, $response));

        $this->status = self::STATUS_TERMINATED;
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
