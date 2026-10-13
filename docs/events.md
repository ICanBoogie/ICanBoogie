# Events

Events are provided by [icanboogie/event](https://github.com/ICanBoogie/Event) and bound by
`icanboogie/bind-event`. The `EventCollection` is a service, available as `$app->events`.

## Emitting

The `ICanBoogie\emit()` function is defined once the application is instantiated. It emits the event
and returns it:

```php
<?php

use function ICanBoogie\emit;

$event = emit(new MyEvent($app));
```

## Attaching hooks

### In configuration

The usual way is an `event.php` config fragment. The framework attaches its cache hook that way:

```php
<?php

// config/event.php

namespace ICanBoogie;

use ICanBoogie\Application\ClearCacheEvent;
use ICanBoogie\Binding\Event\ConfigBuilder;

return fn(ConfigBuilder $config) => $config
    ->attach(ClearCacheEvent::class, [ Hooks::class, 'on_clear_cache' ]);
```

### At runtime

```php
<?php

use ICanBoogie\Application\BootEvent;

$detach = $app->events->attach(function (BootEvent $event) {
    // ...
});

$detach(); // stop listening
```

Hooks receive the event; application events carry the application in their `app` property rather than using a
sender.

## Application events

| Event                         | Purpose                                                                          |
|-------------------------------|----------------------------------------------------------------------------------|
| `Application\BootEvent`       | The application finished booting.                                                |
| `Application\RunEvent`        | The application starts handling a request (`request`).                           |
| `Application\TerminateEvent`  | The response was sent (`request`, `response`).                                   |
| `Application\ClearCacheEvent` | Caches must be cleared. Hooks call `$event->cleared('what')` to report progress. |

See [Application lifecycle](lifecycle.md) for when each is emitted.

## Clearing caches from a hook

```php
<?php

public static function on_clear_cache(ClearCacheEvent $event): void
{
    $event->app->storage_for_configs->clear();
    $event->cleared('app.storage_for_configs');
}
```

`Application::clear_cache()` and the `cache:clear` command both emit this event; messages passed to
`cleared()` are printed by the command.
