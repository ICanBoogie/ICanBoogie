# Application lifecycle

The `Application` moves through a fixed sequence of states. Each state has a boolean property you
can read:

| Property          | True while…                                                  |
|-------------------|--------------------------------------------------------------|
| `is_booting`      | `boot()` is executing                                        |
| `is_booted`       | `boot()` finished and `run()` hasn't been called             |
| `is_running`      | the request is being handled                                 |
| `is_terminating`  | `TerminateEvent` is being emitted                            |
| `is_terminated`   | everything is finished                                       |

## Instantiation

`Application::new(Autoconfig $autoconfig)` (or `boot()`) creates the singleton. In order, the
constructor:

1. sets the default timezone to UTC if none is set;
2. creates the config provider from the autoconfig's config paths and builders;
3. loads `AppConfig` and applies it: installs the error and exception handlers, and, when
  `cache_configs` is enabled, wraps the provider in a `CachedConfigProvider`;
4. creates the service container;
5. defines `ref()` from `icanboogie/service`, so services can be referenced anywhere;
6. obtains the `EventCollection` from the container and defines `emit()`.

## Boot

`$app->boot()`:

1. throws `InvalidState` if the application has already booted;
2. invokes the callables registered with [`autoboot`](autoconfig.md#booting-packages) with the
   application as argument, in dependency order;
3. emits `Application\BootEvent`;
4. stores the time at which boot finished in `$_SERVER['ICANBOOGIE_READY_TIME_FLOAT']`.

## Run

`$app->run(?Request $request = null)`:

1. sets the HTTP status to 500 and sends `no-store` cache headers, so that a fatal error is
   never cached or reported as a 200;
2. throws `InvalidState` if the application hasn't booted, or is already running;
3. stores the request (defaults to `Request::from_server($_SERVER)`) in `$app->request`;
4. emits `Application\RunEvent`;
5. gets the response from the `ICanBoogie\HTTP\Responder` service;
6. when [`Server-Timing`](configuration.md#server-timing) is enabled, lets the providers add their
   metrics to the response;
7. sends the response.

## Terminate

After the response has been sent, the application emits `Application\TerminateEvent` with the
request and the response. Use it for work that should happen after the response is delivered.

## Events

| Event                         | Emitted…                                         | Properties                   |
|-------------------------------|--------------------------------------------------|------------------------------|
| `Application\BootEvent`       | after boot                                       | `app`                        |
| `Application\RunEvent`        | when the application starts running              | `app`, `request`             |
| `Application\TerminateEvent`  | after the response was sent                      | `app`, `request`, `response` |
| `Application\ClearCacheEvent` | by `clear_cache()` and the `cache:clear` command | `app`, `cleared`             |

See [Events](events.md) for how to attach hooks.

## Errors

`Application\InvalidState` is a `LogicException` thrown when a method is called in the wrong state:
not instantiated, already instantiated, not booted, already booted, already running.
