<?php

namespace ICanBoogie;

use ICanBoogie\Application\BootEvent;
use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\Application\InvalidState;
use ICanBoogie\Application\RunEvent;
use ICanBoogie\Application\TerminateEvent;
use ICanBoogie\Autoconfig\Autoconfig;
use ICanBoogie\Binding\SymfonyDependencyInjection\ContainerFactory;
use ICanBoogie\Config\Builder;
use ICanBoogie\ConfigProvider\BasicConfigProvider;
use ICanBoogie\ConfigProvider\CachedConfigProvider;
use ICanBoogie\HTTP\Request;
use ICanBoogie\HTTP\Responder;
use ICanBoogie\HTTP\Response;
use ICanBoogie\HTTP\Status;
use ICanBoogie\Storage\Storage;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
 * The application singleton.
 */
final class Application implements ConfigProvider, ServiceProvider
{
    /**
     * Status of the application.
     */
    private const int STATUS_VOID = 0;
    private const int STATUS_BOOTING = 5;
    private const int STATUS_BOOTED = 6;
    private const int STATUS_RUNNING = 7;
    private const int STATUS_TERMINATING = 8;
    private const int STATUS_TERMINATED = 9;

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
    public bool $is_booting {
        get => $this->status === self::STATUS_BOOTING;
    }

    /**
     * Whether the application is booted.
     */
    public bool $is_booted {
        get => $this->status === self::STATUS_BOOTED;
    }

    /**
     * Whether the application is running.
     */
    public bool $is_running {
        get => $this->status === self::STATUS_RUNNING;
    }

    /**
     * Whether the application is terminating.
     */
    public bool $is_terminating {
        get => $this->status === self::STATUS_TERMINATING;
    }

    /**
     * Whether the application is terminated.
     */
    public bool $is_terminated {
        get => $this->status === self::STATUS_TERMINATED;
    }

    public readonly Autoconfig $autoconfig;

    /**
     * @var Storage<string, mixed>
     */
    public Storage $storage_for_configs {
        get => $this->storage_for_configs
            ??= $this->create_storage($this->config->storage_for_config);
    }

    /**
     * Returns the non-volatile variables registry.
     *
     * @var Storage<string, mixed>
     */
    public Storage $vars {
        get => $this->vars
            ??= $this->create_storage($this->config->storage_for_vars);
    }

    public ConfigProvider $configs;
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
        \ICanBoogie\Service\ServiceProvider::define($this->container->get(...));

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
    private function create_config_provider(array $paths, array $builders): ConfigProvider
    {
        return new BasicConfigProvider(self::sort_config_paths($paths), $builders);
    }

    /**
     * Sorts configuration paths by weight and returns their keys.
     *
     * @param array<string, int> $paths Path list.
     *
     * @return string[]
     */
    private static function sort_config_paths(array $paths): array
    {
        asort($paths, SORT_NUMERIC);

        return array_keys($paths);
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

        if ($config->cache_configs && $this->configs instanceof BasicConfigProvider) {
            $this->configs = new CachedConfigProvider(
                $this->configs,
                $this->storage_for_configs,
                self::sort_config_paths($this->autoconfig->config_paths),
            );
        }
    }

    /**
     * Creates a storage engine, using a factory.
     *
     * @param (callable(Application): Storage<string, mixed>)|null $factory
     *
     * @return Storage<string, mixed>
     */
    private function create_storage(?callable $factory): Storage
    {
        if ($factory === null) {
            throw new LogicException("A storage factory is not configured. Check the `storage_for_*` options of AppConfig.");
        }

        return $factory($this);
    }

    /**
     * Boot the modules and configure Debug, Prototype, and Events.
     *
     * Emits {@see BootEvent} after the boot is finished.
     *
     * The `ICANBOOGIE_READY_TIME_FLOAT` key is added to the `$_SERVER` super global with the
     * micro-time at which the boot finished.
     */
    public function boot(): void
    {
        $this->assert_can_boot();

        $this->status = self::STATUS_BOOTING;

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

    /**
     * The initial request.
     */
    public Request $request {
        get => $this->request ??= Request::from_server();
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
    public function run(?Request $request = null): void
    {
        $this->initialize_response_header();
        $this->assert_can_run();

        $this->status = self::STATUS_RUNNING;
        $this->request = $request ??= Request::from_server();

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
     * The default response has the {@see Status::INTERNAL_SERVER_ERROR} status code and the appropriate
     * header fields, so it is not cached. That way, if something goes wrong and an error message is displayed, it won't
     * be cached by a proxy.
     */
    private function initialize_response_header(): void
    {
        http_response_code(Status::INTERNAL_SERVER_ERROR);

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
     * Emits {@see TerminateEvent}.
     */
    private function terminate(Request $request, Response $response): void
    {
        $this->status = self::STATUS_TERMINATING;

        emit(new TerminateEvent($this, $request, $response));

        $this->status = self::STATUS_TERMINATED;
    }

    /**
     * Emits {@see ClearCacheEvent}
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
