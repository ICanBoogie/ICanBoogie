# Getting started

## Installation

```shell
composer require icanboogie/icanboogie
```

PHP 8.4 or later is required. Installing the package also installs the
[Autoconfig](autoconfig.md) Composer plugin (`icanboogie/autoconfig`), which must be allowed in your
`composer.json`:

```json
{
  "config": {
    "allow-plugins": {
      "icanboogie/autoconfig": true
    }
  }
}
```

Every time Composer installs or updates packages, the plugin regenerates
`vendor/icanboogie/autoconfig.php`.

## Booting and running

The entry point of a web application is typically `web/index.php`:

```php
<?php

namespace ICanBoogie;

require __DIR__ . '/../vendor/autoload.php';

boot()->run();
```

- `boot()` obtains the `Autoconfig`, instantiates the `Application` singleton, and boots it.
- `run()` creates a `Request` from `$_SERVER`, hands it to the HTTP responder, sends the response,
  and terminates the application. See [Application lifecycle](lifecycle.md).

You can pass your own `Autoconfig` to `boot()`, and your own `Request` to `run()`:

```php
<?php

namespace ICanBoogie;

use ICanBoogie\HTTP\Request;

// Change the working directory where you app directory is.
chdir(dirname(__DIR__));

boot($autoconfig)->run();
```

## Helpers

Two functions are defined in the `ICanBoogie` namespace:

- `boot(?Autoconfig $autoconfig = null): Application`: instantiates and boots the application.
- `app(): Application`: returns the application instance. Throws `Application\InvalidState` if the
  application hasn't been instantiated yet.

`Application::get()` does the same as `app()`, and `Application::new($autoconfig)` instantiates
without booting. The application is a singleton: calling `new()` twice throws `InvalidState`.

## Using the application

```php
<?php

use function ICanBoogie\app;

$app = app();

$app->config;      // AppConfig
$app->autoconfig;  // Autoconfig
$app->container;   // Symfony ContainerInterface
$app->events;      // EventCollection
$app->vars;        // Storage for non-volatile variables
$app->request;     // The initial Request
```

Obtain services with `service_for_class()` and `service_for_id()`; see [Services](services.md).
Obtain configurations with `config_for_class()`; see [Configuration](configuration.md).

## Testing

The repository's `tests/bootstrap.php` shows how to boot the framework for PHPUnit: change directory
to the test application, require the autoloader, buffer output so headers aren't sent, and call
`boot()`.

```php
<?php

namespace ICanBoogie;

chdir(__DIR__);

require __DIR__ . '/../vendor/autoload.php';

ob_start(); // Prevent PHPUnit from sending headers

boot();
```
