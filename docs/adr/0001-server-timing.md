# ADR 0001: Expose framework telemetry through the `Server-Timing` header

- **Status:** Accepted
- **Date:** 2026-10-18
- **Target version:** 7.0.0

## Context

`icanboogie/http` supports the `Server-Timing` header (`Headers::$server_timing`, `ServerTiming`,
`ServerTimingMetric`). Several packages already collect timing information, each in its own shape:

| Package        | Component             | Shape                                                                 |
|----------------|-----------------------|-----------------------------------------------------------------------|
| `config`       | `ConfigProfiler`      | Static, `Record[]`, **uninitialized until the first config is built** |
| `event`        | `EventProfiler`       | Static, `CallRecord[]` and `UnusedRecord[]`                           |
| `activerecord` | `ConnectionTelemetry` | **Not static**: one instance per `Connection`, `ExecuteRecord[]`      |

__Unit.__ Measures come from `microtime(true)`, which has microsecond resolution. A start time is a
Unix timestamp such as `1760745600.123456`, and a duration is a difference of two such values, such
as `0.0123` for 12.3 ms. `Server-Timing` `dur` is in milliseconds, so providers multiply by 1000
(`0.0123` becomes `12.3`) and must not truncate. `ServerTimingMetric` rounds the duration to 2 decimals
when it renders the header, so providers do not round.

__Nesting.__ Records carry a start time and a duration, and are added when the measured work ends.
Work can nest (a listener emits an event, a config builder requests a config), so summing durations
counts nested work twice.

__Uninitialized records.__ `ConfigProfiler::$records` has no default value, reading it before a
config is built throws. With cached configurations it may never be initialized.

Nothing feeds this data to the browser dev tools. We want the framework to do so, as an opt-in
feature.

Constraints:

1. **Dependency direction.** `http` → `event` → `config`, and `activerecord` → `config`.
   `config` is the only common root and cannot depend on `http`. An interface in `http` cannot be
   implemented by `config`, `event` or `activerecord`.
2. **The telemetry packages stay unaware of `Server-Timing`.** Presentation is not their concern, and
   they would otherwise each gain a dependency on `http`.
3. **Every `bind-*` package requires `icanboogie/icanboogie`** and the package it binds, so it can see
   both the framework and the telemetry.
4. **`Server-Timing` is readable by any client**, including cross-origin with `Timing-Allow-Origin`. It
   reveals timing and counts about the internals of the application.
5. **Headers are final once `$response($request)` sends them.** `TerminateEvent` is emitted afterwards.

## Decision drivers

- Opt-in, off by default.
- Telemetry packages untouched.
- Consistent with how the framework is configured (`AppConfigBuilder`) and wired (containers, `bind-*`).
- A third-party package can contribute metrics without touching the framework.

## Considered options

### Option A: Interface in `config`, implemented by the telemetry components

The static classes implement a shared interface. **Rejected**: `config` cannot name `ServerTiming`,
so the interface would need a neutral metric type, and the telemetry packages would still carry
presentation concerns. It also does not fit `ConnectionTelemetry`, which is not static.

### Option B: Interface in `http`

**Rejected**: same dependency problem, `config` and `event` cannot implement it.

### Option C: Providers in the `bind-*` packages, interface in `icanboogie` — **chosen**

`icanboogie` defines the interface and runs the providers. Each `bind-*` package ships a provider
that adapts its package's telemetry. The telemetry packages do not change.

### Option D: A listener on a response event instead of an explicit step in `Application::run()`

**Rejected**: emitting an event disturbs what `EventProfiler` measures.

- Every listener call is recorded, so the timing listener is profiled while it reads the profiler,
  and its own call is missing or partial depending on listener order.
- With no listener attached, the emission is recorded as an unused event that exists only to produce
  the measurement. The `N unused` and `N calls` figures would include the work done to produce them.
- The listener order is hard to control, while the header must be altered after the response exists
  and before it is sent.

An explicit step emits nothing, which avoids all three.

## Decision

### Interface

In `icanboogie`:

```php
namespace ICanBoogie;

use ICanBoogie\HTTP\Headers\ServerTiming;

interface ServerTimingProvider
{
    /**
     * Adds metrics to the timing of the response.
     */
    public function alter(ServerTiming $timing): void;
}
```

Providers are services resolved from the container, so they receive their dependencies (a
`ConnectionIterator`, for instance) through constructor injection.
`Application::service_for_class()` uses `$container->get()`, so each provider **must** be declared
in the package's `services.yml` with `public: true`:

```yaml
ICanBoogie\Binding\Event\EventServerTimingProvider:
  public: true
```

Without it, the container does not expose the service and `get()` throws when the feature is
enabled. This applies to third-party providers too.

`#[Autoconfigure(public: true)]` on the interface does not replace it: Symfony reads attributes on
the service's own class, and the interface is not registered through a `resource:` glob.

### Configuration

`AppConfig` gets `bool $server_timing = false` and `list<class-string<ServerTimingProvider>>
$server_timing_providers = []`. `AppConfigBuilder` gets:

```php
$builder
    ->enable_server_timing()
    ->disable_server_timing()
    ->add_server_timing_provider(ConfigServerTimingProvider::class)
    ->remove_server_timing_provider(ConfigServerTimingProvider::class);
```

`add_server_timing_provider()` appends to the list, and adding the same class twice is a no-op.
`remove_server_timing_provider()` lets an application drop a provider that a package added.
Adding a provider does not enable the feature: providers are only resolved when it is on.

### Registration

Packages add their provider through an `app` config fragment, not during `autoboot`. `AppConfig` is
readonly and built before the application boots, so `autoboot` would need a second, mutable
registry. A fragment is declarative, part of the built (and cached) configuration, costs nothing per
request, and follows the pattern of `icanboogie/config/app.php`. The application's fragment is
applied after those of the packages, so it can remove what they added.

```php
<?php

// bind-event/config/app.php

namespace ICanBoogie;

return fn(AppConfigBuilder $config) => $config
    ->add_server_timing_provider(EventServerTimingProvider::class);
```

`icanboogie/config/app.php` adds `AppServerTimingProvider` and `ConfigServerTimingProvider` the same
way. Installing a `bind-*` package is enough for its metrics to appear once the application calls
`enable_server_timing()`.

### Application

`Application::run()` gets one step between `respond()` and `$response($request)`:

```php
$response = $this->service_for_class(Responder::class)->respond($request);

if ($this->config->server_timing) {
    // resolve the providers, and call `alter()` on each
}

$response($request);
```

When `config->server_timing` is `false`, nothing is resolved and the header is not read. Otherwise the
step resolves **all** configured providers from the container, then calls
`alter($response->headers->server_timing)` on each. Resolving first keeps the
cost of building providers (configs, connections) out of what the telemetry providers read. A config
built during an `alter()` call, after `ConfigServerTimingProvider` has read the records, is missed
by the `config` metric. This is a documented, known inaccuracy.

The step does nothing else: even the `app` metric is a provider. Providers add to the header, so
metrics set by the responder are kept.

### Providers

Providers aggregate, one metric per kind of data, so the header stays small and does not leak
details such as class names or SQL.

| Package             | Provider                           | Metric                                                           |
|---------------------|------------------------------------|------------------------------------------------------------------|
| `icanboogie`        | `AppServerTimingProvider`          | `app;dur=…`, from `REQUEST_TIME_FLOAT` to now                    |
| `icanboogie`        | `ConfigServerTimingProvider`       | `config;dur=…;desc="N configs"`, from `ConfigProfiler`           |
| `bind-event`        | `EventServerTimingProvider`        | `event;dur=…;desc="N used, N unused, N calls"`                   |
| `bind-activerecord` | `ActiveRecordServerTimingProvider` | `db;dur=…;desc="N queries"`, sum of the established connections  |

`dur` of `config` and `event` is the length of the **union** of the `[start, start + duration]`
intervals of the records, so nested work is counted once. For `db`, queries on one connection do not
overlap, so durations are summed, then summed across connections. The `event` records come from
`EventProfiler::iter_calls()`, and its description reports, in this order:

- **used**: the number of distinct event types with at least one listener call;
- **unused**: the number of distinct event types emitted with no listener, from
  `EventProfiler::iter_unused()`. `EventCollection` marks a type as skippable once it is recorded, so
  repeated emissions of the same type are not counted again. They have no duration, so they only
  appear in the description. A high count points at event types emitted for nothing;
- **calls**: the number of listener calls.

`AppServerTimingProvider` reads `$_SERVER['REQUEST_TIME_FLOAT']` and adds nothing when it is missing.
It measures the total up to the point where the headers are final, including the time spent
resolving providers. It is the first provider added, so it reads the clock before the others run.

`AppServerTimingProvider` and `ConfigServerTimingProvider` live in `icanboogie`, which already
requires `config`, and are added by its `config/app.php`. A provider adds a metric only when it has
data to report; `ConfigServerTimingProvider` tolerates an uninitialized `ConfigProfiler::$records`.

The ActiveRecord provider takes the `ConnectionIterator` and reads `telemetry` only from connections
whose `ConnectionAccessor::$instantiated` is `true`, through `ConnectionAccessor::get()`, so it never
establishes a connection. `Connection::$telemetry` is public, so `activerecord` does not change.

### Failure handling

Exceptions thrown by a provider are not caught: they propagate out of `Application::run()` and the
response is not sent. The feature is opt-in and meant for development and staging, where the
exception handler already makes a failure visible. Catching exceptions would need a reporting
channel (a log or an error metric), and both are easier to miss than the exception itself. The
feature is not meant to run in production, so a failing metric does not need to be survivable.

### Security

The documentation states that `Server-Timing` exposes internal timings to every client, and that the
feature is meant for development and staging only. It is off by default and should not be enabled in
production.

## Consequences

**Positive**

- Telemetry packages are unchanged, and `config`, `event` and `activerecord` keep their
  dependencies.
- Opt-in at two levels: the global switch, and each provider.
- Third parties extend it by implementing one interface and registering it.
- The disabled path costs one boolean check.

**Neutral**

- Metrics are aggregated: no per-query or per-listener detail in the header.
- `ConnectionTelemetry` and the profilers record unconditionally, so enabling `Server-Timing` does
  not change the collection overhead.
- Provider exceptions propagate, see "Failure handling".
- `Application::run()` gains a step with a feature-specific name.
- Error responses get the header only when the exception is turned into a response inside
  `Responder::respond()`, typically by an exception handler registered with the `WithRescue`
  middleware. `Application::run()` does not handle exceptions itself.
- Long-running workers are not supported. The profilers are static and never reset, and
  `REQUEST_TIME_FLOAT` is the start of the process, so metrics would accumulate across requests. This
  is acceptable because such workers are not meant to serve HTTP. It could be improved in the future,
  for instance by resetting the profilers after each response.
- The header covers the work that precedes the response. Headers are sent before the body, so
  sending cannot be measured, and work done in `TerminateEvent` is not included.

**Negative**

- Each `bind-*` package that wants to contribute needs a provider and an `app` config fragment.
- Installing a `bind-*` package adds its provider implicitly. An application that does not want it
  must call `remove_server_timing_provider()`.

## Implementation plan

1. `icanboogie`: `ServerTimingProvider`, `AppServerTimingProvider`, `ConfigServerTimingProvider`,
   their public service definitions in `config/services.yml`, `AppConfig` and `AppConfigBuilder`
   options, `config/app.php`, the `Application::run()` step, tests, docs (`docs/configuration.md`,
   `docs/lifecycle.md`), `CHANGELOG.md`.
2. `bind-event`: `EventServerTimingProvider`, public service definition, `config/app.php`, tests,
   README, `CHANGELOG.md`.
3. `bind-activerecord`: `ActiveRecordServerTimingProvider`, public service definition,
   `config/app.php`, tests, `CHANGELOG.md`. `ConnectionAccessor` already exposes `instantiated`, so
   `activerecord` does not change.

## Tests

- With the feature disabled, no provider is resolved and the response has no `Server-Timing` header.
- With the feature enabled and no providers, the response has no `Server-Timing` header.
- `AppServerTimingProvider` adds `app` from `REQUEST_TIME_FLOAT`, and nothing when it is missing.
- After `remove_server_timing_provider(AppServerTimingProvider::class)`, there is no `app` metric.
- Providers are called in the order they were added, and adding a class twice calls it once.
- A provider added by one fragment and removed by a later one is not called.
- The `app` config of an application with the `bind-event` fragment contains `EventServerTimingProvider`.
- Each provider shipped by a package is resolvable from the container of an application that
  enables the feature, that is, its service is public.
- Metrics already present on the response are kept.
- An exception thrown by a provider propagates out of `Application::run()`.
- `enable_server_timing()` followed by `disable_server_timing()` disables the feature, and removing a
  provider that was never added is a no-op.
- Each provider: converts seconds to milliseconds without truncating (`0.0123` gives `12.3`), adds
  nothing when there is no data, and reports the expected count in `desc`.
- Nested records (a listener emitting an event, a config requesting a config) are counted once in `dur`.
- `ConfigServerTimingProvider` adds nothing, and does not throw, when `ConfigProfiler::$records` is
  uninitialized.
- The event provider reports used, unused and calls in the description of a single `event` metric,
  and counts distinct types for used and unused.
- No event is emitted to produce the header: `EventProfiler` has the same records before and after.
- The ActiveRecord provider does not establish connections that were not already established.
