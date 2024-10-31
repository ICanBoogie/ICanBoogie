# CHANGELOG

## v5.x to v6.0

### New Requirements

- PHP 8.2+

### New features

- Added `AppConfigBuilder`.
- Added `DebugConfigBuilder` and `DebugConfig`.
- Added the `ServiceProvider` interface.
- `Application` implements `ConfigProvider`, `ServiceProvider`.
- Added the console commands `cache:clear` and `configs:list` (alias `configs`).

### Backward Incompatible Changes

- Renamed `ApplicationAbstract` as `Application`. The concept of extending `Application` extending
`ApplicationAbstract` to add bindings is gone, as bindings are being phased out in favor of dependency injection container usage.

- The Autoconfig is now represented by a `Autoconfig` instance instead of an array and is available under `Application::$autoconfig`. `Application::$config` is now a `AppConfig` instance instead of an array, and it no longer includes Autoconfig parameters. `AppConfig` constants that were used as array keys are now removed. Also, `ICanBoogie\AUTOCONFIG_PATHNAME` has been replaced with `ICANBOOGIE_AUTOCONFIG`.

- The `EventCollection` instance is now obtained from the container. The `Application::$events` property is now a real property, not a prototype method.

- `Application` events no long use a sender and include a `app` property instead.

    ```php
    <?php

    $events->attach(function (BootEvent $event, Application $sender) { ... });
    ```
    ```php
    <?php

    $events->attach(function (BootEvent $event) { ... });
    ```

- The constructor of `Application` is now private and the class is final. Use `Application::new()` instead.

- Removed `PrototypedBindings` and `get_app` on `Prototyped`.

- Removed functions to generate tokens: `generate_token`, `generate_token_wide`.

- Removed debug features.

- Removed logger features.

- Removed `AppAccessor`.

- Removed JavaScript assets.

- Remove function `strip_root`.

### Deprecated Features

None

### Other Changes

- `get_autoconfig` tries multiple places, including `ICANBOOGIE_AUTOCONFIG` if it is defined. PHPUnit can be used as a package now.
- Variable data files are now store in `var` instead of `repository`, to mimic Linux.
- The autoconfig features have been moved to `icanboogie/autoconfig`.
  It is a Composer plugin that listens to `post-autoload-dump` events.
  It is no longer required to specify the event in ICanBoogie projects.
