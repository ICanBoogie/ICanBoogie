# Migration

## v5.x to v6.x

### New features

- Added `AppConfigBuilder`.
- Added `DebugConfigBuilder` and `DebugConfig`.

### Backward Incompatible Changes

- `ApplicationAbstract::$config` is now a `AppConfig` instance instead of an array, and it does no
  longer include Autoconfig parameters, which are now available under
  `ApplicationAbstract::$auto_config`. `AppConfig` constants that were used as array keys are now
  removed.

- `Application` events no long use a sender and include a `app` property instead.

    ```php
    <?php

    $events->attach(function (BootEvent $event, Application $sender) { ... });
    ```
    ```php
    <?php

    $events->attach(function (BootEvent $event) { ... });
    ```

### Deprecated Features

N/A

### Other Changes

N/A
