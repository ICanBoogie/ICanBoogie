# Migration

## v5.x to v6.x

### New features

- Added `AppConfigBuilder`.
- Added `DebugConfigBuilder` and `DebugConfig`.
- Added the `ServiceProvider` interface
- `Application` implements `ConfigProvider`, `ServiceProvider`.

### Backward Incompatible Changes

- Renamed `ApplicationAbstract` as `Application`. The concept of extending `Application` extending `ApplicationAbstract` to add binding is gone, as bindings are being phased out in favor of dependency injection container usage.

- `Application::$config` is now a `AppConfig` instance instead of an array, and it does no longer include Autoconfig parameters, which are now available under `Application::$auto_config`. `AppConfig` constants that were used as array keys are now removed.

- `Application` events no long use a sender and include a `app` property instead.

    ```php
    <?php

    $events->attach(function (BootEvent $event, Application $sender) { ... });
    ```
    ```php
    <?php

    $events->attach(function (BootEvent $event) { ... });
    ```

- Removed `ICanBoogie\AUTOCONFIG_PATHNAME`. It's been replaced with `ICANBOOGIE_AUTOCONFIG`.

### Deprecated Features

None

### Other Changes

- `get_autoconfig` tries multiple places, including `ICANBOOGIE_AUTOCONFIG` if it is defined. PHPUnit can be used as a package now.
